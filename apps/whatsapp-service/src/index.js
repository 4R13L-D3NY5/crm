import express from 'express';
import cors from 'cors';
import pino from 'pino';
import QRCode from 'qrcode';
import axios from 'axios';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as baileys from '@whiskeysockets/baileys';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const makeWASocket = baileys.default || baileys.makeWASocket || baileys;
const {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  Browsers,
  makeCacheableSignalKeyStore,
} = baileys;

const app = express();
const PORT = process.env.PORT || 3000;
const LARAVEL_WEBHOOK_URL =
  process.env.LARAVEL_WEBHOOK_URL ||
  'http://api:8010/api/whatsapp/baileys/webhook';
const SESSIONS_DIR = path.resolve(__dirname, '../sessions');

if (!fs.existsSync(SESSIONS_DIR)) {
  fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

app.use(cors());
app.use(express.json());

// In-memory sessions store
// sessions[id] = { sock, qrRaw, qrImage, status, phone, name }
const sessions = {};

async function notifyLaravel(payload) {
  try {
    await axios.post(LARAVEL_WEBHOOK_URL, payload, { timeout: 6000 });
  } catch (err) {
    console.error(
      `[Webhook Notification Failed] event=${payload.event} error=${err.message}`,
    );
  }
}

async function initSession(sessionId) {
  if (sessions[sessionId]?.sock) {
    return sessions[sessionId];
  }

  const sessionPath = path.join(SESSIONS_DIR, sessionId);
  if (!fs.existsSync(sessionPath)) {
    fs.mkdirSync(sessionPath, { recursive: true });
  }

  const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
  let version = [2, 3000, 1043857760];
  try {
    const fetched = await fetchLatestBaileysVersion();
    version = fetched.version;
  } catch (e) {
    console.warn(`[Baileys Version Fallback] using cached version: ${version}`);
  }

  const logger = pino({ level: 'silent' });

  const sock = makeWASocket({
    version,
    logger,
    printQRInTerminal: false,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger),
    },
    browser: Browsers ? Browsers.ubuntu('Chrome') : ['Ubuntu', 'Chrome', '22.04.4'],
    generateHighQualityLinkPreview: false,
    syncFullHistory: false,
    defaultQueryTimeoutMs: undefined,
  });

  sessions[sessionId] = {
    sock,
    qrRaw: null,
    qrImage: null,
    status: 'CONNECTING',
    phone: null,
    name: null,
  };

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      try {
        const qrImage = await QRCode.toDataURL(qr, { width: 320, margin: 2 });
        if (sessions[sessionId]) {
          sessions[sessionId].qrRaw = qr;
          sessions[sessionId].qrImage = qrImage;
          sessions[sessionId].status = 'CONNECTING';
        }

        await notifyLaravel({
          event: 'qr',
          account_id: sessionId,
          qr_raw: qr,
          qr_image: qrImage,
        });
      } catch (err) {
        console.error(`[QR Generation Error] ${err.message}`);
      }
    }

    if (connection === 'open') {
      const userJid = sock.user?.id || '';
      const cleanPhone = userJid.split(':')[0].split('@')[0];
      const name = sock.user?.name || sock.user?.notify || '';

      if (sessions[sessionId]) {
        sessions[sessionId].status = 'CONNECTED';
        sessions[sessionId].qrRaw = null;
        sessions[sessionId].qrImage = null;
        sessions[sessionId].phone = cleanPhone ? `+${cleanPhone}` : null;
        sessions[sessionId].name = name;
      }

      console.log(`[WhatsApp Connected] account=${sessionId} phone=+${cleanPhone} name=${name}`);

      await notifyLaravel({
        event: 'connected',
        account_id: sessionId,
        phone: cleanPhone ? `+${cleanPhone}` : '',
        name,
      });
    }

    if (connection === 'close') {
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      const wasConnected = sessions[sessionId]?.status === 'CONNECTED';
      const isLoggedOut = statusCode === DisconnectReason.loggedOut;

      console.log(
        `[WhatsApp Connection Closed] account=${sessionId} statusCode=${statusCode} wasConnected=${wasConnected} isLoggedOut=${isLoggedOut}`,
      );

      // Remover el socket inerte para permitir reconexión limpia
      if (sessions[sessionId]?.sock) {
        try {
          sessions[sessionId].sock.ws?.close?.();
        } catch (_) {}
        delete sessions[sessionId].sock;
      }

      if (isLoggedOut) {
        // 401: Credenciales rechazadas por WhatsApp o sesión cerrada en el celular
        // Limpiamos los archivos para evitar bucles con credenciales caducadas
        try {
          fs.rmSync(sessionPath, { recursive: true, force: true });
        } catch (_) {}

        if (wasConnected) {
          if (sessions[sessionId]) {
            sessions[sessionId].status = 'DISCONNECTED';
            sessions[sessionId].qrRaw = null;
            sessions[sessionId].qrImage = null;
            delete sessions[sessionId];
          }

          await notifyLaravel({
            event: 'disconnected',
            account_id: sessionId,
            reason: 'logged_out',
          });
        } else {
          // Si nunca estuvo conectado, reiniciar con credenciales limpias tras 3s
          if (sessions[sessionId]) {
            sessions[sessionId].status = 'CONNECTING';
            sessions[sessionId].qrRaw = null;
            sessions[sessionId].qrImage = null;
          }

          setTimeout(() => {
            if (!sessions[sessionId]?.sock) {
              initSession(sessionId).catch(console.error);
            }
          }, 3000);
        }
      } else {
        // Desconexión temporal (ej: 515 restartRequired, 408 timeout de QR, desconexión de red)
        // PRESERVAR credenciales en disco (vital para completar el emparejamiento 515)
        if (sessions[sessionId]) {
          sessions[sessionId].status = 'CONNECTING';
          sessions[sessionId].qrRaw = null;
          sessions[sessionId].qrImage = null;
        }

        const delay = statusCode === DisconnectReason.restartRequired ? 1000 : 2000;
        setTimeout(() => {
          if (!sessions[sessionId]?.sock) {
            initSession(sessionId).catch(console.error);
          }
        }, delay);
      }
    }
  });

  // Listener para mensajes entrantes de WhatsApp
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (!Array.isArray(messages)) return;

    for (const msg of messages) {
      if (!msg.key || msg.key.remoteJid === 'status@broadcast') continue;
      // No reinyectar mensajes enviados por el CRM
      if (msg.key.fromMe) continue;

      const remoteJid = msg.key.remoteJid || '';
      if (!remoteJid.endsWith('@s.whatsapp.net')) {
        continue;
      }

      const rawPhone = remoteJid.replace('@s.whatsapp.net', '').replace('@c.us', '');
      const cleanPhone = `+${rawPhone}`;

      const body =
        msg.message?.conversation ||
        msg.message?.extendedTextMessage?.text ||
        msg.message?.imageMessage?.caption ||
        msg.message?.videoMessage?.caption ||
        '';

      if (body && body.trim().length > 0) {
        console.log(`[Inbound Message Received] account=${sessionId} from=${cleanPhone} body=${body}`);

        await notifyLaravel({
          event: 'message',
          account_id: sessionId,
          provider_message_id: msg.key.id,
          from_phone: cleanPhone,
          from_name: msg.pushName || cleanPhone,
          body: body.trim(),
          timestamp: msg.messageTimestamp,
        });
      }
    }
  });

  return sessions[sessionId];
}

// REST Endpoints
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    activeSessions: Object.keys(sessions).length,
    sessions: Object.keys(sessions).map((id) => ({
      id,
      status: sessions[id].status,
      phone: sessions[id].phone,
    })),
  });
});

app.post('/sessions/:sessionId/start', async (req, res) => {
  const { sessionId } = req.params;
  try {
    await initSession(sessionId);

    // Esperar un momento si el QR se está generando
    let tries = 0;
    while (tries < 10 && !sessions[sessionId]?.qrRaw && sessions[sessionId]?.status === 'CONNECTING') {
      await new Promise((r) => setTimeout(r, 200));
      tries++;
    }

    const session = sessions[sessionId] || { status: 'CONNECTING' };
    res.json({
      status: session.status,
      qrcode_raw: session.qrImage || session.qrRaw,
      qr_image: session.qrImage,
      phone: session.phone,
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/sessions/:sessionId/qr', async (req, res) => {
  const { sessionId } = req.params;
  let session = sessions[sessionId];

  if (!session || !session.sock) {
    try {
      await initSession(sessionId);
      let tries = 0;
      while (tries < 10 && !sessions[sessionId]?.qrRaw && sessions[sessionId]?.status === 'CONNECTING') {
        await new Promise((r) => setTimeout(r, 200));
        tries++;
      }
      session = sessions[sessionId] || { status: 'CONNECTING' };
    } catch (err) {
      return res.status(500).json({ error: err.message });
    }
  }

  res.json({
    account_id: sessionId,
    status: session.status,
    qrcode_raw: session.qrImage || session.qrRaw,
    qr_image: session.qrImage,
    phone: session.phone,
    name: session.name,
  });
});

app.post('/sessions/:sessionId/pairing-code', async (req, res) => {
  const { sessionId } = req.params;
  const { phone } = req.body;

  if (!phone) {
    return res.status(400).json({ error: 'Número de teléfono es requerido' });
  }

  const cleanPhone = phone.replace(/[^0-9]/g, '');
  try {
    const sessionPath = path.join(SESSIONS_DIR, sessionId);
    const wasConnected = sessions[sessionId]?.status === 'CONNECTED';

    // Si aún no está vinculado, cerramos cualquier socket previo y limpiamos credenciales residuales
    if (!wasConnected) {
      if (sessions[sessionId]?.sock) {
        try {
          sessions[sessionId].sock.ws?.close?.();
        } catch (_) {}
        delete sessions[sessionId].sock;
      }
      try {
        fs.rmSync(sessionPath, { recursive: true, force: true });
      } catch (_) {}
      delete sessions[sessionId];
    }

    const session = await initSession(sessionId);

    // Esperar a que el WebSocket de WhatsApp esté completamente conectado
    let waitReady = 0;
    while (waitReady < 25 && (!sessions[sessionId]?.sock?.ws || sessions[sessionId].sock.ws.readyState !== 1)) {
      await new Promise((r) => setTimeout(r, 200));
      waitReady++;
    }

    const currentSock = sessions[sessionId]?.sock;
    if (!currentSock) {
      return res.status(500).json({ error: 'Socket no disponible. Por favor intenta de nuevo.' });
    }

    await new Promise((r) => setTimeout(r, 800));
    const code = await currentSock.requestPairingCode(cleanPhone);
    console.log(`[Pairing Code Generated] account=${sessionId} phone=${cleanPhone} code=${code}`);
    res.json({ success: true, pairing_code: code });
  } catch (err) {
    console.error(`[Pairing Code Error] ${err.message}`);
    res.status(500).json({ error: err.message || 'Error al solicitar el código de vinculación' });
  }
});

app.post('/sessions/:sessionId/send-message', async (req, res) => {
  const { sessionId } = req.params;
  const { to, text } = req.body;

  if (!to || !text) {
    return res.status(400).json({ error: 'Parámetros "to" y "text" son requeridos' });
  }

  const session = sessions[sessionId];
  if (!session || !session.sock || session.status !== 'CONNECTED') {
    return res.status(400).json({ error: 'La sesión no está conectada a WhatsApp' });
  }

  try {
    const cleanTo = to.replace(/[^0-9]/g, '');
    const jid = `${cleanTo}@s.whatsapp.net`;

    const sent = await session.sock.sendMessage(jid, { text });
    res.json({
      success: true,
      messageId: sent?.key?.id,
      timestamp: sent?.messageTimestamp,
    });
  } catch (err) {
    console.error(`[Error Sending Message] ${err.message}`);
    res.status(500).json({ error: err.message });
  }
});

app.post('/sessions/:sessionId/logout', async (req, res) => {
  const { sessionId } = req.params;
  const session = sessions[sessionId];

  try {
    if (session?.sock) {
      await session.sock.logout();
    }
  } catch (_) {}

  delete sessions[sessionId];
  const sessionPath = path.join(SESSIONS_DIR, sessionId);
  try {
    fs.rmSync(sessionPath, { recursive: true, force: true });
  } catch (_) {}

  res.json({ success: true, message: 'Sesión cerrada correctamente' });
});

// Auto-restaurar sesiones guardadas al reiniciar (solo si ya tenían credenciales vinculadas)
fs.readdir(SESSIONS_DIR, (err, files) => {
  if (!err && files) {
    for (const dir of files) {
      const fullPath = path.join(SESSIONS_DIR, dir);
      const credsPath = path.join(fullPath, 'creds.json');
      if (fs.statSync(fullPath).isDirectory() && fs.existsSync(credsPath)) {
        console.log(`[Auto-restoring Active Session] ${dir}`);
        initSession(dir).catch((e) =>
          console.error(`[Auto-restore Failed] ${dir}: ${e.message}`),
        );
      }
    }
  }
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`[WhatsApp Baileys Service] escuchando en puerto ${PORT}`);
});
