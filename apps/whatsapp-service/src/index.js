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
  extractMessageContent,
  jidNormalizedUser,
  downloadMediaMessage,
} = baileys;

process.on('unhandledRejection', (reason) => {
  console.warn('[Process Unhandled Rejection Ignored]', reason);
});
process.on('uncaughtException', (err) => {
  console.error('[Process Uncaught Exception Handled]', err.message);
});

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
app.use(express.json({ limit: '50mb' }));

// In-memory sessions store
// sessions[id] = { sock, qrRaw, qrImage, status, phone, name }
const sessions = {};

// In-memory set to track outbound messages sent via CRM API to avoid echo reflection loops
const sentByCrmMessageIds = new Map();

function trackSentByCrm(messageId) {
  if (!messageId) return;
  const now = Date.now();
  sentByCrmMessageIds.set(messageId, now);

  // Periodic pruning of entries older than 5 minutes
  if (sentByCrmMessageIds.size > 200) {
    const cutoff = now - 5 * 60 * 1000;
    for (const [id, timestamp] of sentByCrmMessageIds.entries()) {
      if (timestamp < cutoff) {
        sentByCrmMessageIds.delete(id);
      }
    }
  }
}

function isSentByCrm(messageId) {
  if (!messageId) return false;
  return sentByCrmMessageIds.has(messageId);
}

async function notifyLaravel(payload) {
  try {
    await axios.post(LARAVEL_WEBHOOK_URL, payload, {
      timeout: 30000,
      maxBodyLength: Infinity,
      maxContentLength: Infinity,
    });
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

  // Mapa de nombres de contactos y mapeo bidireccional LID <-> Teléfono con persistencia en disco
  const lidMappingFile = path.join(sessionPath, 'lid-mapping.json');
  let contactsMap = {};
  let lidToPhoneMap = {};
  let phoneToLidMap = {};

  try {
    if (fs.existsSync(lidMappingFile)) {
      const persisted = JSON.parse(fs.readFileSync(lidMappingFile, 'utf-8'));
      lidToPhoneMap = persisted.lidToPhone || {};
      phoneToLidMap = persisted.phoneToLid || {};
      contactsMap = persisted.contacts || {};
    }
  } catch (err) {
    console.warn(`[LID Mapping Load Warning] account=${sessionId}: ${err.message}`);
  }

  function saveLidMapping() {
    try {
      fs.writeFileSync(
        lidMappingFile,
        JSON.stringify({ lidToPhone: lidToPhoneMap, phoneToLid: phoneToLidMap, contacts: contactsMap }, null, 2),
        'utf-8'
      );
    } catch (err) {
      console.warn(`[LID Mapping Save Warning] account=${sessionId}: ${err.message}`);
    }
  }

  function registerContactMapping(c) {
    if (!c) return;
    let modified = false;
    if (c.id) {
      const prevName = contactsMap[c.id];
      const newName = c.name || c.notify || c.verifiedName || prevName;
      if (newName && newName !== prevName) {
        contactsMap[c.id] = newName;
        modified = true;
      }
    }
    // Mapear si el contacto contiene tanto @lid como @s.whatsapp.net
    if (c.lid && c.id && c.id.endsWith('@s.whatsapp.net')) {
      const phone = '+' + c.id.split('@')[0].split(':')[0];
      const lidClean = c.lid.split('@')[0].split(':')[0];
      if (lidToPhoneMap[lidClean] !== phone) {
        lidToPhoneMap[lidClean] = phone;
        phoneToLidMap[phone] = lidClean;
        modified = true;
      }
    }
    if (c.phoneNumber && c.id && c.id.endsWith('@lid')) {
      const phone = '+' + c.phoneNumber.replace(/[^0-9]/g, '');
      const lidClean = c.id.split('@')[0].split(':')[0];
      if (lidToPhoneMap[lidClean] !== phone) {
        lidToPhoneMap[lidClean] = phone;
        phoneToLidMap[phone] = lidClean;
        modified = true;
      }
    }
    if (modified) {
      saveLidMapping();
    }
  }

  sock.ev.on('contacts.upsert', (contacts) => {
    if (!Array.isArray(contacts)) return;
    for (const c of contacts) {
      registerContactMapping(c);
    }
  });

  sock.ev.on('contacts.update', (updates) => {
    if (!Array.isArray(updates)) return;
    for (const u of updates) {
      registerContactMapping(u);
    }
  });

  // Listener para mensajes entrantes de WhatsApp
  sock.ev.on('messages.upsert', async ({ messages, type }) => {
    if (!Array.isArray(messages)) return;
    console.log(`[messages.upsert] account=${sessionId} type=${type} total=${messages.length}`);

    for (const msg of messages) {
      if (!msg.key) continue;
      const remoteJid = msg.key.remoteJid || '';

      // Ignorar estados, canales, difusiones masivas y grupos (@g.us)
      if (
        !remoteJid ||
        remoteJid === 'status@broadcast' ||
        remoteJid.includes('broadcast') ||
        remoteJid.includes('newsletter') ||
        remoteJid.endsWith('@g.us')
      ) {
        continue;
      }

      const isFromMe = Boolean(msg.key.fromMe);

      // Si el mensaje fue enviado desde el CRM vía API, descartar el evento espejo para:
      // 1) Evitar re-descargar el multimedia desde los servidores de WhatsApp (ahorro masivo de tiempo y CPU)
      // 2) Evitar duplicación de mensaje en la bandeja del CRM
      if (isFromMe && isSentByCrm(msg.key.id)) {
        console.log(`[Skipping Echo: Sent by CRM] account=${sessionId} messageId=${msg.key.id}`);
        continue;
      }

      console.log(`[Processing Message] id=${msg.key.id} fromMe=${isFromMe} remoteJid=${remoteJid} participant=${msg.key.participant || 'none'}`);

      // Normalizar número de teléfono emisor / interlocutor
      let cleanPhone = '';
      let lid = null;

      if (remoteJid.endsWith('@s.whatsapp.net')) {
        cleanPhone = '+' + (jidNormalizedUser ? jidNormalizedUser(remoteJid) : remoteJid).split('@')[0].split(':')[0];
      } else if (remoteJid.endsWith('@lid')) {
        lid = remoteJid.split('@')[0].split(':')[0];

        // 1) Intentar extraer número telefónico real desde metadatos senderPn / participantPn que Baileys decodifica
        const pnCandidate = msg.key?.senderPn || msg.key?.participantPn || msg.senderPn || msg.participantPn;
        if (pnCandidate) {
          const rawPn = pnCandidate.split('@')[0].split(':')[0].replace(/[^0-9]/g, '');
          if (rawPn.length >= 8 && rawPn.length <= 13) {
            cleanPhone = `+${rawPn}`;
            lidToPhoneMap[lid] = cleanPhone;
            phoneToLidMap[cleanPhone] = lid;
            saveLidMapping();
          }
        }

        // 2) En mensajes entrantes (!isFromMe), participant puede traer el teléfono real @s.whatsapp.net
        if (!cleanPhone && !isFromMe && msg.key.participant && msg.key.participant.endsWith('@s.whatsapp.net')) {
          cleanPhone = '+' + (jidNormalizedUser ? jidNormalizedUser(msg.key.participant) : msg.key.participant).split('@')[0].split(':')[0];
          lidToPhoneMap[lid] = cleanPhone;
          phoneToLidMap[cleanPhone] = lid;
          saveLidMapping();
        } else if (!cleanPhone && lidToPhoneMap[lid]) {
          cleanPhone = lidToPhoneMap[lid];
        } else if (!cleanPhone) {
          cleanPhone = `+${lid}`;
        }
      } else {
        cleanPhone = `+${remoteJid.split('@')[0].split(':')[0]}`;
      }

      // Evitar procesar mensajes enviados a uno mismo ("Mensajes guardados / Notas propias")
      const myUserPhone = sock.user?.id ? sock.user.id.split(':')[0].split('@')[0] : '';
      const myUserLid = sock.user?.lid ? sock.user.lid.split(':')[0].split('@')[0] : '';
      const cleanPhoneDigits = cleanPhone.replace(/[^0-9]/g, '');
      if (
        (myUserPhone && cleanPhoneDigits === myUserPhone.replace(/[^0-9]/g, '')) ||
        (myUserLid && (cleanPhoneDigits === myUserLid.replace(/[^0-9]/g, '') || lid === myUserLid))
      ) {
        continue;
      }

      // Desempaquetar contenido de mensaje (efímero, viewOnce, documento, captions, texto)
      const content = (extractMessageContent ? extractMessageContent(msg.message) : null) || msg.message || {};

      // Detectar edición de mensaje entrante (protocolMessage con editedMessage)
      const protocolMsg = content.protocolMessage || msg.message?.protocolMessage;
      if (protocolMsg && protocolMsg.editedMessage) {
        const targetId = protocolMsg.key?.id;
        const editedContent = (extractMessageContent ? extractMessageContent(protocolMsg.editedMessage) : null) || protocolMsg.editedMessage || {};
        const newBody = editedContent.conversation || editedContent.extendedTextMessage?.text || '';
        if (targetId && newBody) {
          console.log(`[Incoming Message Edited Event] account=${sessionId} targetId=${targetId} newBody=${newBody.trim()}`);
          await notifyLaravel({
            event: 'message_edited',
            account_id: sessionId,
            target_message_id: targetId,
            body: newBody.trim(),
            from_phone: cleanPhone,
            from_me: isFromMe,
          });
          continue;
        }
      }

      // Detectar tipo multimedia
      let mediaType = null;
      let mimeType = null;
      let durationSeconds = null;
      let fileName = null;

      if (content.imageMessage) {
        mediaType = 'image';
        mimeType = content.imageMessage.mimetype || 'image/jpeg';
      } else if (content.audioMessage) {
        mediaType = 'audio';
        mimeType = content.audioMessage.mimetype || 'audio/ogg';
        durationSeconds = content.audioMessage.seconds || null;
      } else if (content.videoMessage) {
        mediaType = 'video';
        mimeType = content.videoMessage.mimetype || 'video/mp4';
        durationSeconds = content.videoMessage.seconds || null;
      } else if (content.documentMessage) {
        mediaType = 'document';
        mimeType = content.documentMessage.mimetype || 'application/pdf';
        fileName = content.documentMessage.fileName || 'documento';
      } else if (content.stickerMessage) {
        mediaType = 'sticker';
        mimeType = content.stickerMessage.mimetype || 'image/webp';
      }

      let mediaBuffer = null;
      if (mediaType && downloadMediaMessage) {
        try {
          mediaBuffer = await downloadMediaMessage(
            msg,
            'buffer',
            {},
            {
              logger,
              reuploadRequest: sock ? (m) => sock.updateMediaMessage(m) : undefined,
            }
          );
          if (mediaBuffer && mediaBuffer.length > 0) {
            console.log(`[Media Downloaded] type=${mediaType} bytes=${mediaBuffer.length}`);
          }
        } catch (downloadErr) {
          console.warn(`[Media Download Fallback] id=${msg.key.id}: ${downloadErr.message}`);
          try {
            mediaBuffer = await downloadMediaMessage(
              { key: msg.key, message: content },
              'buffer',
              {},
              { logger }
            );
          } catch (e2) {
            console.warn(`[Media Download Fallback 2 Failed] ${e2.message}`);
          }
        }
      }

      const body =
        content.conversation ||
        content.extendedTextMessage?.text ||
        content.imageMessage?.caption ||
        content.videoMessage?.caption ||
        content.documentMessage?.caption ||
        content.buttonsResponseMessage?.selectedButtonId ||
        content.listResponseMessage?.singleSelectReply?.selectedRowId ||
        content.templateButtonReplyMessage?.selectedId ||
        (content.imageMessage ? '[Imagen]' : '') ||
        (content.audioMessage ? '[Nota de voz / Audio]' : '') ||
        (content.videoMessage ? '[Video]' : '') ||
        (content.documentMessage ? `[Documento: ${content.documentMessage?.fileName || 'archivo'}]` : '') ||
        (content.stickerMessage ? '[Sticker]' : '') ||
        (content.contactMessage ? '[Contacto]' : '') ||
        (content.locationMessage ? '[Ubicación]' : '') ||
        '';

      const effectiveBody = (body && body.trim().length > 0)
        ? body.trim()
        : (mediaType === 'image' ? '[Imagen]' : (mediaType === 'audio' ? '[Nota de voz / Audio]' : (mediaType === 'sticker' ? '[Sticker]' : '')));

      // Extracción de metadatos de Campaña / Anuncio Patrocinado (Meta Ads / Click-to-WhatsApp)
      let referral = null;
      const contextInfo =
        content.extendedTextMessage?.contextInfo ||
        content.imageMessage?.contextInfo ||
        content.videoMessage?.contextInfo ||
        content.documentMessage?.contextInfo;

      const externalAdReply = contextInfo?.externalAdReply;
      if (externalAdReply) {
        let thumbBase64 = null;
        if (externalAdReply.jpegThumbnail) {
          try {
            thumbBase64 = Buffer.isBuffer(externalAdReply.jpegThumbnail)
              ? externalAdReply.jpegThumbnail.toString('base64')
              : Buffer.from(externalAdReply.jpegThumbnail).toString('base64');
          } catch (e) {
            // ignore
          }
        }

        referral = {
          source_type: externalAdReply.sourceType ? String(externalAdReply.sourceType).toLowerCase() : 'ad',
          source_id: externalAdReply.sourceId ? String(externalAdReply.sourceId) : null,
          source_url: externalAdReply.sourceUrl || null,
          headline: externalAdReply.title || null,
          body: externalAdReply.body || null,
          media_type: externalAdReply.mediaType === 2 ? 'video' : 'image',
          image_url: externalAdReply.thumbnailUrl || externalAdReply.mediaUrl || null,
          thumbnail_base64: thumbBase64 ? `data:image/jpeg;base64,${thumbBase64}` : null,
        };
        console.log(`[Meta Ad Referral Detected] account=${sessionId} headline="${referral.headline}" source=${referral.source_type} url=${referral.source_url}`);
      }

      if (effectiveBody && effectiveBody.length > 0) {
        console.log(`[${isFromMe ? 'Outbound Mirror' : 'Inbound'} Message Received] account=${sessionId} phone=${cleanPhone} type=${mediaType || 'text'} body=${effectiveBody}`);

        await notifyLaravel({
          event: 'message',
          account_id: sessionId,
          provider_message_id: msg.key.id,
          from_phone: cleanPhone,
          remote_jid: remoteJid,
          lid: lid,
          from_name: msg.pushName || contactsMap[remoteJid] || (msg.key.participant ? contactsMap[msg.key.participant] : null) || cleanPhone,
          from_me: isFromMe,
          body: effectiveBody,
          timestamp: msg.messageTimestamp,
          media_type: mediaType,
          media_base64: mediaBuffer ? mediaBuffer.toString('base64') : null,
          mime_type: mimeType,
          media_duration_seconds: durationSeconds,
          file_name: fileName,
          referral: referral,
        });
      } else {
        console.log(`[Inbound Message Skipped: Empty Body] rawKeys=`, Object.keys(msg.message || {}));
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
    let jid;
    if (to.includes('@lid') || cleanTo.length >= 14) {
      jid = `${cleanTo}@lid`;
    } else {
      jid = `${cleanTo}@s.whatsapp.net`;
    }

    console.log(`[Sending Outbound Message] account=${sessionId} to=${jid} text=${text}`);
    const customMessageId = (baileys.generateMessageID && typeof baileys.generateMessageID === 'function')
      ? baileys.generateMessageID()
      : undefined;

    if (customMessageId) {
      trackSentByCrm(customMessageId);
    }

    const sent = await session.sock.sendMessage(jid, { text }, customMessageId ? { messageId: customMessageId } : undefined);
    const finalId = sent?.key?.id || customMessageId;
    if (finalId) {
      trackSentByCrm(finalId);
    }
    console.log(`[Outbound Message Sent] account=${sessionId} messageId=${finalId}`);

    res.json({
      success: true,
      messageId: finalId,
      timestamp: sent?.messageTimestamp || Math.floor(Date.now() / 1000),
    });
  } catch (err) {
    console.error(`[Error Sending Message] ${err.message}`);
    res.status(500).json({ error: err.message });
  }
});

app.post('/sessions/:sessionId/edit-message', async (req, res) => {
  const { sessionId } = req.params;
  const { to, messageId, text } = req.body;

  if (!to || !messageId || !text) {
    return res.status(400).json({ error: 'Parámetros "to", "messageId" y "text" son requeridos' });
  }

  const session = sessions[sessionId];
  if (!session || !session.sock || session.status !== 'CONNECTED') {
    return res.status(400).json({ error: 'La sesión no está conectada a WhatsApp' });
  }

  try {
    const cleanTo = to.replace(/[^0-9]/g, '');
    let jid;
    if (to.includes('@lid') || cleanTo.length >= 14) {
      jid = `${cleanTo}@lid`;
    } else {
      jid = `${cleanTo}@s.whatsapp.net`;
    }

    console.log(`[Editing Outbound Message] account=${sessionId} to=${jid} messageId=${messageId} text=${text}`);
    const key = {
      remoteJid: jid,
      id: messageId,
      fromMe: true,
    };
    const edited = await session.sock.sendMessage(jid, { text, edit: key });
    const finalEditId = edited?.key?.id || messageId;
    if (finalEditId) {
      trackSentByCrm(finalEditId);
    }
    console.log(`[Outbound Message Edited] account=${sessionId} messageId=${finalEditId}`);

    res.json({
      success: true,
      messageId: finalEditId,
      timestamp: edited?.messageTimestamp || Math.floor(Date.now() / 1000),
    });
  } catch (err) {
    console.error(`[Error Editing Message] ${err.message}`);
    res.status(500).json({ error: err.message });
  }
});

app.post('/sessions/:sessionId/send-media', async (req, res) => {
  const { sessionId } = req.params;
  const { to, media_url, media_type, caption, file_name, media_base64, mime_type } = req.body;

  if (!to || (!media_url && !media_base64)) {
    return res.status(400).json({ error: 'Parámetros "to" y "media_url" o "media_base64" son requeridos' });
  }

  const session = sessions[sessionId];
  if (!session || !session.sock || session.status !== 'CONNECTED') {
    return res.status(400).json({ error: 'La sesión no está conectada a WhatsApp' });
  }

  try {
    const cleanTo = to.replace(/[^0-9]/g, '');
    let jid;
    if (to.includes('@lid') || cleanTo.length >= 14) {
      jid = `${cleanTo}@lid`;
    } else {
      jid = `${cleanTo}@s.whatsapp.net`;
    }

    console.log(`[Sending Outbound Media] account=${sessionId} to=${jid} type=${media_type} url=${media_url ? media_url.slice(0, 60) : 'base64'}`);

    let mediaSource;
    if (media_base64) {
      mediaSource = Buffer.from(media_base64, 'base64');
    } else if (media_type === 'sticker' && media_url) {
      try {
        const resp = await axios.get(media_url, { responseType: 'arraybuffer' });
        mediaSource = Buffer.from(resp.data);
      } catch (_) {
        mediaSource = { url: media_url };
      }
    } else {
      mediaSource = { url: media_url };
    }

    let messagePayload;
    const cleanCaption = (caption && caption !== '[Imagen]' && caption !== '[Nota de voz / Audio]' && caption !== '[Documento]' && caption !== '[Sticker]') ? caption : '';

    if (media_type === 'image') {
      messagePayload = {
        image: mediaSource,
        caption: cleanCaption,
      };
    } else if (media_type === 'audio') {
      messagePayload = {
        audio: mediaSource,
        mimetype: mime_type || 'audio/mp4',
        ptt: true,
      };
    } else if (media_type === 'document') {
      messagePayload = {
        document: mediaSource,
        mimetype: mime_type || 'application/pdf',
        fileName: file_name || 'documento.pdf',
        caption: cleanCaption,
      };
    } else if (media_type === 'sticker') {
      messagePayload = {
        sticker: mediaSource,
      };
    } else {
      messagePayload = {
        image: mediaSource,
        caption: cleanCaption,
      };
    }

    const customMessageId = (baileys.generateMessageID && typeof baileys.generateMessageID === 'function')
      ? baileys.generateMessageID()
      : undefined;

    if (customMessageId) {
      trackSentByCrm(customMessageId);
    }

    const sent = await session.sock.sendMessage(jid, messagePayload, customMessageId ? { messageId: customMessageId } : undefined);
    const finalId = sent?.key?.id || customMessageId;
    if (finalId) {
      trackSentByCrm(finalId);
    }
    console.log(`[Outbound Media Sent Success] account=${sessionId} messageId=${finalId}`);

    res.json({
      success: true,
      messageId: finalId,
      timestamp: sent?.messageTimestamp || Math.floor(Date.now() / 1000),
    });
  } catch (err) {
    console.error(`[Error Sending Media] ${err.message}`);
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
