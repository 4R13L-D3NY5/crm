<template>
  <q-card flat bordered class="whatsapp-channel-card">
    <q-card-section class="q-pb-sm">
      <div class="row items-start justify-between no-wrap q-gutter-x-sm">
        <!-- Icono y Datos de Línea / Canal -->
        <div class="row items-center q-gutter-x-md col no-wrap overflow-hidden" style="min-width: 0">
          <div
            class="channel-icon shrink-0"
            :class="[
              `channel-icon--${platformMeta.themeClass}`,
              `channel-icon--${account.status.toLowerCase()}`,
            ]"
          >
            <q-icon :name="platformMeta.icon" size="22px" :style="{ color: platformMeta.color }" />
          </div>

          <div class="col overflow-hidden" style="min-width: 0">
            <div class="row items-center q-gutter-x-xs no-wrap">
              <span class="channel-name ellipsis text-weight-bold text-white cursor-pointer" :title="account.name" style="max-width: calc(100% - 105px)" @click="openEditDialog">
                {{ account.name }}
              </span>
              <q-btn flat round dense size="xs" icon="sym_r_edit" color="grey-4" @click.stop="openEditDialog">
                <q-tooltip class="text-caption">Editar datos del canal</q-tooltip>
              </q-btn>
              <span class="platform-badge shrink-0" :style="{ borderColor: platformMeta.color + '44', color: platformMeta.color }">
                {{ platformMeta.label }}
              </span>
            </div>
            <div class="channel-number text-caption text-grey-4 ellipsis cursor-pointer" @click="openEditDialog" title="Clic para editar número visible">
              {{ channelDisplayIdentifier }}
            </div>
          </div>
        </div>

        <!-- Menú de Opciones -->
        <q-btn flat round dense icon="sym_r_more_vert" color="grey-4" size="sm" class="shrink-0">
          <q-tooltip class="text-caption">Opciones del Canal</q-tooltip>
          <q-menu dark dense class="channel-menu">
            <q-list>
              <q-item
                clickable
                v-close-popup
                @click="openEditDialog"
              >
                <q-item-section avatar><q-icon name="sym_r_edit" size="16px" color="teal-4" /></q-item-section>
                <q-item-section>Editar Datos del Canal</q-item-section>
              </q-item>

              <q-item
                clickable
                v-close-popup
                @click="showCredentialsDialog = true"
              >
                <q-item-section avatar><q-icon name="sym_r_key" size="16px" color="teal-4" /></q-item-section>
                <q-item-section>Ver Webhook & Tokens</q-item-section>
              </q-item>

              <q-item
                clickable
                v-close-popup
                @click="openAssignUsersDialog"
              >
                <q-item-section avatar><q-icon name="sym_r_group_add" size="16px" color="indigo-3" /></q-item-section>
                <q-item-section>Asignar Operadores</q-item-section>
              </q-item>

              <q-item
                v-if="account.session_type === 'facebook'"
                clickable
                v-close-popup
                class="text-blue-4"
                @click="handleSyncFacebook"
              >
                <q-item-section avatar><q-icon name="sym_r_sync" size="16px" color="blue-4" /></q-item-section>
                <q-item-section>Sincronizar Mensajes</q-item-section>
              </q-item>

              <q-item
                v-if="account.session_type === 'instagram'"
                clickable
                v-close-popup
                class="text-grey-3"
                @click="handleSyncInstagram"
              >
                <q-item-section avatar><q-icon name="sym_r_sync" size="16px" color="grey-3" /></q-item-section>
                <q-item-section>Sincronizar DMs Instagram</q-item-section>
              </q-item>


              <q-item
                clickable
                v-close-popup
                @click="emit('simulate', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_send" size="16px" color="primary" /></q-item-section>
                <q-item-section>Simular Mensaje Entrante</q-item-section>
              </q-item>

              <q-separator dark class="q-my-xs" />

              <q-item
                v-if="isQrSession && account.status === 'CONNECTED'"
                clickable
                v-close-popup
                class="text-amber-4"
                @click="emit('disconnect', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_link_off" size="16px" /></q-item-section>
                <q-item-section>Desconectar Sesión</q-item-section>
              </q-item>
              <q-item
                v-else-if="!isQrSession && account.status === 'CONNECTED'"
                clickable
                v-close-popup
                class="text-amber-4"
                @click="emit('disconnect', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_link_off" size="16px" /></q-item-section>
                <q-item-section>Desactivar Canal</q-item-section>
              </q-item>
              <q-item
                v-else-if="isQrSession"
                clickable
                v-close-popup
                class="text-teal-4"
                @click="emit('connect', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_qr_code" size="16px" /></q-item-section>
                <q-item-section>Conectar vía QR</q-item-section>
              </q-item>

              <q-separator dark class="q-my-xs" />
              <q-item
                clickable
                v-close-popup
                class="text-negative"
                @click="confirmDelete"
              >
                <q-item-section avatar><q-icon name="sym_r_delete" size="16px" color="negative" /></q-item-section>
                <q-item-section>Eliminar Canal</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>

      <!-- Badge de Estado Minimalista -->
      <div class="row items-center justify-between q-mt-md">
        <div class="status-pill" :class="`status-pill--${account.status.toLowerCase()}`">
          <span class="status-dot"></span>
          <span>{{ statusLabel }}</span>
        </div>

        <div v-if="account.last_connected_at" class="text-caption text-grey-5" style="font-size: 0.7rem">
          {{ formatDate(account.last_connected_at) }}
        </div>
      </div>

      <!-- Anti-Ban Guard: Contador de Chats y Límite Diario -->
      <div v-if="account.anti_ban" class="anti-ban-section q-mt-sm q-pa-sm">
        <div class="row items-center justify-between text-caption q-mb-xs">
          <div class="row items-center q-gutter-x-xs">
            <q-icon
              :name="account.anti_ban.risk_level === 'danger' ? 'sym_r_warning' : (account.anti_ban.risk_level === 'warning' ? 'sym_r_error' : 'sym_r_shield')"
              size="15px"
              :color="antiBanColor"
            />
            <span class="text-caption text-bold" :class="`text-${antiBanColor}`">
              Anti-Ban Guard
            </span>
            <span v-if="account.anti_ban.is_qr" class="text-grey-5" style="font-size: 0.68rem">
              (QR)
            </span>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <span class="text-bold text-white font-mono" style="font-size: 0.73rem">
              {{ account.anti_ban.chats_today }} / {{ account.anti_ban.daily_limit }} chats
            </span>
            <q-btn
              flat
              dense
              round
              icon="sym_r_settings"
              size="xs"
              color="grey-4"
              @click.stop="openEditLimitDialog"
            >
              <q-tooltip class="bg-dark text-white">Configurar límite diario de chats</q-tooltip>
            </q-btn>
          </div>
        </div>

        <q-linear-progress
          :value="account.anti_ban.usage_percent / 100"
          rounded
          size="5px"
          :color="antiBanColor"
          track-color="dark"
        />

        <div class="row items-center justify-between text-grey-5 q-mt-xs" style="font-size: 0.68rem">
          <span>{{ account.anti_ban.usage_percent }}% del límite diario</span>
          <span>{{ account.anti_ban.outbound_today }} msgs salientes</span>
        </div>
      </div>

      <!-- Operadores Asignados al Canal -->
      <div class="row items-center justify-between q-mt-sm q-pt-xs" style="border-top: 1px solid rgba(255,255,255,0.05)">
        <div class="row items-center q-gutter-x-xs ellipsis" style="max-width: 78%">
          <q-icon name="sym_r_badge" size="14px" color="indigo-3" />
          <span v-if="account.authorized_users && account.authorized_users.length" class="text-caption text-grey-3 ellipsis" style="font-size: 0.72rem">
            Asignado a: <strong class="text-white">{{ account.authorized_users.map(u => u.name).join(', ') }}</strong>
          </span>
          <span v-else class="text-caption text-grey-5 italic" style="font-size: 0.72rem">
            Sin asignar (Visible a todos)
          </span>
        </div>

        <q-btn
          flat
          dense
          no-caps
          size="xs"
          color="indigo-3"
          icon="sym_r_person_add"
          label="Asignar"
          @click.stop="openAssignUsersDialog"
        />
      </div>
    </q-card-section>

    <!-- Footer con Acciones Rápidas -->
    <q-separator dark class="q-mt-sm" style="border-color: var(--crm-color-border)" />
    <q-card-actions align="between" class="q-px-md q-py-sm">
      <div class="row items-center q-gutter-x-xs">
        <q-btn
          v-if="account.session_type === 'facebook'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_sync"
          label="Sincronizar"
          color="grey-3"
          :loading="isSyncing"
          @click="handleSyncFacebook"
        />
        <q-btn
          v-if="account.session_type === 'instagram'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_sync"
          label="Sincronizar"
          color="grey-3"
          :loading="isSyncing"
          @click="handleSyncInstagram"
        />
        <q-btn
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_send"
          label="Simular Entrada"
          color="teal-4"
          @click="emit('simulate', account)"
        />
        <q-btn
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_edit"
          label="Editar"
          color="teal-4"
          @click="openEditDialog"
        />
        <q-btn
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_key"
          label="Webhook"
          color="grey-4"
          @click="showCredentialsDialog = true"
        />
      </div>

      <div class="row items-center q-gutter-x-xs">
        <q-btn
          v-if="isQrSession && account.status === 'CONNECTED'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_link_off"
          label="Desconectar"
          color="grey-5"
          @click="emit('disconnect', account.id)"
        />
        <q-btn
          v-else-if="isQrSession"
          unelevated
          dense
          size="sm"
          no-caps
          icon="sym_r_qr_code_scanner"
          label="Escanear QR"
          class="xf-btn-primary q-px-sm"
          @click="emit('connect', account)"
        />
        <q-btn
          v-else
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_delete"
          label="Eliminar Canal"
          color="negative"
          @click="confirmDelete"
        >
          <q-tooltip>Eliminar o desvincular este canal del CRM</q-tooltip>
        </q-btn>
      </div>
    </q-card-actions>

    <!-- Modal de Credenciales & Webhook del Canal -->
    <q-dialog v-model="showCredentialsDialog">
      <q-card style="width: 520px; max-width: 95vw" class="credentials-card">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" :style="{ background: platformMeta.color + '22' }">
              <q-icon :name="platformMeta.icon" size="16px" :style="{ color: platformMeta.color }" />
            </q-avatar>
            <div>
              <div class="text-subtitle2 text-weight-bold text-white">
                Configuración Técnica: {{ account.name }}
              </div>
              <div class="text-caption text-grey-4">{{ platformMeta.label }}</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-md q-gutter-y-sm">
          <!-- Webhook Callback URL -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">URL de Callback (Webhook)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(webhookUrl)"
              />
            </div>
            <q-input
              :model-value="webhookUrl"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
            <div class="text-caption text-grey-5 q-mt-xs" style="font-size: 0.72rem">
              Pega esta URL en el portal de desarrolladores para recibir mensajes en tiempo real.
            </div>
          </div>

          <!-- Token de Verificación (Verify Token) -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Token de Verificación (Verify Token)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.verify_token || 'xpertiflow_secret_token')"
              />
            </div>
            <q-input
              :model-value="account.verify_token || 'xpertiflow_secret_token'"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Identificador de Canal (Phone Number ID o Page ID) -->
          <div v-if="account.phone_number_id">
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Identificador (Page / Phone ID)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.phone_number_id)"
              />
            </div>
            <q-input
              :model-value="account.phone_number_id"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Business ID -->
          <div v-if="account.business_account_id">
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Business Account / App Key</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.business_account_id)"
              />
            </div>
            <q-input
              :model-value="account.business_account_id"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Tip de Configuración -->
          <div class="instruction-box q-pa-sm q-mt-sm">
            <q-icon name="sym_r_info" size="16px" color="teal-4" class="q-mr-xs" />
            <span class="text-caption text-grey-4">
              {{ platformMeta.instructions }}
            </span>
          </div>
        </q-card-section>

        <q-card-actions align="between" class="q-px-md q-py-sm">
          <q-btn
            flat
            label="Eliminar Canal"
            color="negative"
            icon="sym_r_delete"
            no-caps
            v-close-popup
            @click="confirmDelete"
          />
          <q-btn flat label="Entendido" color="teal-4" no-caps v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal para Configurar Límite Anti-Ban -->
    <q-dialog v-model="showLimitDialog">
      <q-card style="width: 420px; max-width: 95vw" class="credentials-card">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" color="teal-9" text-color="teal-2" icon="sym_r_shield" />
            <div>
              <div class="text-subtitle2 text-weight-bold text-white">Límite Diario Anti-Ban</div>
              <div class="text-caption text-grey-4">{{ account.name }}</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-md q-gutter-y-sm">
          <p class="text-caption text-grey-4 q-mb-sm">
            Establece el número máximo de chats que esta línea debe atender al día para proteger tu número de penalizaciones o bloqueos de WhatsApp.
          </p>

          <q-input
            v-model.number="newDailyLimit"
            type="number"
            outlined
            dark
            dense
            min="10"
            max="10000"
            label="Límite diario de chats"
            hint="Recomendado para QR: 80 - 150 chats/día"
          >
            <template #prepend>
              <q-icon name="sym_r_speed" color="teal-4" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-py-sm">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Límite"
            color="primary"
            no-caps
            :loading="isUpdatingLimit"
            @click="handleSaveLimit"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal para Asignar Operadores al Canal -->
    <q-dialog v-model="showAssignUsersDialog">
      <q-card style="width: 480px; max-width: 95vw; max-height: 85vh;" class="credentials-card column no-wrap">
        <q-card-section class="row items-center justify-between q-pb-none col-auto">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" color="indigo-9" text-color="indigo-2" icon="sym_r_group_add" />
            <div>
              <div class="text-subtitle2 text-weight-bold text-white">Asignar Operadores al Canal</div>
              <div class="text-caption text-grey-4">{{ account.name }}</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-md col scroll">
          <p class="text-caption text-grey-4 q-mb-sm">
            Selecciona qué agentes u operadores se encargarán de atender los chats, mensajes y comentarios de este canal (Facebook, Instagram o WhatsApp).
          </p>

          <div v-if="isLoadingUsers" class="row justify-center q-py-md">
            <q-spinner-dots size="30px" color="primary" />
          </div>

          <div v-else-if="availableUsers.length === 0" class="text-caption text-grey-5 text-center q-py-md">
            No hay operadores registrados en la organización.
          </div>

          <q-list v-else dense class="q-gutter-y-xs">
            <q-item
              v-for="user in availableUsers"
              :key="user.id"
              tag="label"
              class="rounded-borders bg-dark-soft q-pa-sm"
            >
              <q-item-section side top>
                <q-checkbox v-model="selectedUserIds" :val="user.id" dark dense color="primary" />
              </q-item-section>

              <q-item-section>
                <q-item-label class="text-weight-bold text-white text-caption">{{ user.name }}</q-item-label>
                <q-item-label caption class="text-grey-5" style="font-size: 0.7rem">{{ user.email }} • Rol: {{ user.role }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-py-sm col-auto">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Asignación"
            color="primary"
            no-caps
            :loading="isSavingUsers"
            @click="handleSaveAssignedUsers"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal para Editar Datos del Canal -->
    <q-dialog v-model="showEditDialog">
      <q-card style="width: 520px; max-width: 95vw; max-height: 90vh;" class="credentials-card column no-wrap">
        <q-card-section class="row items-center justify-between q-pb-none col-auto">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" :style="{ background: platformMeta.color + '22' }">
              <q-icon :name="platformMeta.icon" size="16px" :style="{ color: platformMeta.color }" />
            </q-avatar>
            <div>
              <div class="text-subtitle2 text-weight-bold text-white">Editar Datos del Canal</div>
              <div class="text-caption text-grey-4">{{ platformMeta.label }}</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-form class="col column no-wrap overflow-hidden" @submit.prevent="handleSaveEdit">
          <q-card-section class="q-pt-md col scroll q-gutter-y-md">
            <!-- Nombre del Canal -->
            <div>
              <label class="text-caption text-grey-4 q-mb-xs block">Nombre del Canal / Identificador *</label>
              <q-input
                v-model="editForm.name"
                outlined
                dark
                dense
                placeholder="Ej. Whatsapp Ivirgarzama"
                :rules="[val => !!val && !!val.trim() || 'El nombre del canal es obligatorio']"
              >
                <template #prepend>
                  <q-icon name="sym_r_label" size="16px" color="teal-4" />
                </template>
              </q-input>
            </div>

            <!-- Número Visible / Identificador de Línea -->
            <div>
              <div class="row items-center justify-between q-mb-xs">
                <label class="text-caption text-grey-4 block">
                  {{ isQrSession ? 'Número de Teléfono Visible' : 'Identificador / Usuario Visible' }}
                </label>
                <span class="text-caption text-teal-4" style="font-size: 0.68rem">Editable</span>
              </div>
              <q-input
                v-model="editForm.display_phone_number"
                outlined
                dark
                dense
                placeholder="Ej. +591 72227760"
              >
                <template #prepend>
                  <q-icon name="sym_r_call" size="16px" color="teal-4" />
                </template>
              </q-input>
              <div class="text-caption text-grey-5 q-mt-xs" style="font-size: 0.72rem">
                Este es el número que se muestra en la tarjeta del canal, selector de chats y reportes.
              </div>
            </div>

            <!-- Límite Diario Anti-Ban -->
            <div v-if="account.session_type === 'baileys_qr' || !['facebook', 'instagram', 'tiktok'].includes(account.session_type)">
              <div class="row items-center justify-between q-mb-xs">
                <label class="text-caption text-grey-4 block">Límite Diario Anti-Ban (Chats / día)</label>
                <span class="text-caption text-teal-4" style="font-size: 0.7rem">Recomendado QR: 80 - 150</span>
              </div>
              <q-input
                v-model.number="editForm.daily_limit"
                type="number"
                outlined
                dark
                dense
                min="10"
                max="10000"
                placeholder="100"
              >
                <template #prepend>
                  <q-icon name="sym_r_shield" size="16px" color="teal-4" />
                </template>
              </q-input>
              <div class="text-caption text-grey-5 q-mt-xs" style="font-size: 0.7rem">
                Previene bloqueos del algoritmo de WhatsApp limitando los chats nuevos por día.
              </div>
            </div>

            <!-- Campos Técnicos Específicos según Plataforma -->
            <template v-if="account.session_type === 'meta_cloud'">
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">Phone Number ID (Meta)</label>
                <q-input
                  v-model="editForm.phone_number_id"
                  outlined
                  dark
                  dense
                  placeholder="ID de número de WhatsApp Cloud"
                />
              </div>
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">WABA Account ID</label>
                <q-input
                  v-model="editForm.business_account_id"
                  outlined
                  dark
                  dense
                  placeholder="ID de WhatsApp Business Account"
                />
              </div>
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">Actualizar Access Token (Opcional)</label>
                <q-input
                  v-model="editForm.access_token"
                  outlined
                  dark
                  dense
                  type="password"
                  placeholder="Dejar en blanco para conservar el token actual"
                />
              </div>
            </template>

            <template v-else-if="account.session_type === 'facebook' || account.session_type === 'instagram'">
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">Page ID / Account ID</label>
                <q-input
                  v-model="editForm.phone_number_id"
                  outlined
                  dark
                  dense
                  placeholder="ID de Fanpage o Cuenta"
                />
              </div>
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">Actualizar Access Token (Opcional)</label>
                <q-input
                  v-model="editForm.access_token"
                  outlined
                  dark
                  dense
                  type="password"
                  placeholder="Dejar en blanco para conservar el token actual"
                />
              </div>
            </template>

            <template v-else-if="account.session_type === 'tiktok'">
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">TikTok Client Key / App ID</label>
                <q-input
                  v-model="editForm.business_account_id"
                  outlined
                  dark
                  dense
                  placeholder="Client Key de TikTok"
                />
              </div>
              <div>
                <label class="text-caption text-grey-4 q-mb-xs block">Actualizar Access Token (Opcional)</label>
                <q-input
                  v-model="editForm.access_token"
                  outlined
                  dark
                  dense
                  type="password"
                  placeholder="Dejar en blanco para conservar el token actual"
                />
              </div>
            </template>
          </q-card-section>

          <q-card-actions align="right" class="q-px-md q-py-sm col-auto" style="border-top: 1px solid rgba(255,255,255,0.06)">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Guardar Cambios"
              color="primary"
              no-caps
              :loading="isSavingEdit"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-card>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useQueryClient } from '@tanstack/vue-query'
import { http } from '@/shared/api/http'
import {
  assignUsersToWhatsAppAccount,
  syncFacebookMessages,
  syncInstagramMessages,
  updateWhatsAppDailyLimit,
  updateWhatsAppAccountById,
  type UpdateWhatsAppAccountPayload,
} from '../api/whatsapp.api'
import type { WhatsAppAccount } from '../types/whatsapp.types'

const props = defineProps<{
  account: WhatsAppAccount
}>()

const emit = defineEmits<{
  (e: 'connect', account: WhatsAppAccount): void
  (e: 'disconnect', id: string): void
  (e: 'simulate', account: WhatsAppAccount): void
  (e: 'delete', id: string): void
}>()

const $q = useQuasar()
const showCredentialsDialog = ref(false)

const platformMeta = computed(() => {
  switch (props.account.session_type) {
    case 'meta_cloud':
      return {
        label: 'Meta Cloud API',
        icon: 'sym_r_cloud',
        color: '#10b981',
        themeClass: 'whatsapp',
        instructions: 'En Meta Developers > WhatsApp > Configuración: ingresa la URL de Callback, el Token de Verificación y suscríbete al campo "messages".',
      }
    case 'facebook':
      return {
        label: 'Facebook Fanpage',
        icon: 'sym_r_public',
        color: '#1877f2',
        themeClass: 'facebook',
        instructions: 'En Meta Developers > Webhooks > Page: suscríbete a los campos "messages", "messaging_postbacks" y "feed" para capturar comentarios.',
      }
    case 'instagram':
      return {
        label: 'Instagram Direct',
        icon: 'sym_r_photo_camera',
        color: '#e1306c',
        themeClass: 'instagram',
        instructions: 'En Meta Developers > Webhooks > Instagram: suscríbete a "messages", "comments" y "mentions". Asegúrate que la cuenta IG esté vinculada a tu Fanpage.',
      }
    case 'tiktok':
      return {
        label: 'TikTok Business',
        icon: 'sym_r_music_note',
        color: '#25f4ee',
        themeClass: 'tiktok',
        instructions: 'En TikTok for Business Developers > Webhook Subscription: registra el endpoint de webhook y suscríbete a eventos de interacción y mensajería.',
      }
    case 'baileys_qr':
    default:
      return {
        label: 'WhatsApp (QR)',
        icon: 'sym_r_qr_code',
        color: '#10b981',
        themeClass: 'whatsapp',
        instructions: 'Vinculación directa mediante socket Baileys. Escanea el código QR desde Dispositivos Vinculados en tu celular.',
      }
  }
})

const isQrSession = computed(() => {
  return props.account.session_type === 'baileys_qr' || props.account.session_type === 'qr_baileys' || props.account.session_type === 'qr'
})

function confirmDelete() {
  if (typeof $q?.dialog === 'function') {
    $q.dialog({
      title: '¿Eliminar canal?',
      message: `¿Estás seguro de que deseas eliminar "${props.account.name}"? El canal se desvinculará del CRM y quedará disponible para volver a vincularse si lo requieres.`,
      persistent: true,
      dark: true,
      ok: {
        label: 'Eliminar',
        color: 'negative',
        flat: false,
      },
      cancel: {
        label: 'Cancelar',
        color: 'grey-4',
        flat: true,
      },
    }).onOk(() => {
      emit('delete', props.account.id)
    })
  } else if (window.confirm(`¿Estás seguro de que deseas eliminar "${props.account.name}"? El canal se desvinculará del CRM.`)) {
    emit('delete', props.account.id)
  }
}

const channelDisplayIdentifier = computed(() => {
  if (props.account.display_phone_number) {
    return props.account.display_phone_number
  }
  if (props.account.phone_number_id) {
    return `ID: ${props.account.phone_number_id}`
  }
  return props.account.session_type === 'baileys_qr' ? 'Escanea para vincular' : 'Sin identificador'
})

const webhookUrl = computed(() => {
  if (props.account.session_type === 'meta_cloud') {
    return props.account.webhook_url || `${window.location.origin}/api/whatsapp/webhook`
  }
  return props.account.social_webhook_url || `${window.location.origin}/api/social/comments/webhook`
})

const statusLabel = computed(() => {
  switch (props.account.status) {
    case 'CONNECTED':
      return 'Canal Conectado'
    case 'CONNECTING':
      return 'Sincronizando...'
    case 'DISCONNECTED':
    default:
      return 'Desconectado'
  }
})

function copyToClipboard(text: string) {
  if (!text) return
  navigator.clipboard.writeText(text).then(() => {
    $q.notify({
      type: 'positive',
      message: 'Copiado al portapapeles',
      position: 'bottom-right',
      timeout: 1800,
    })
  })
}

function formatDate(dateStr: string) {
  try {
    const d = new Date(dateStr)
    return `Activo: ${d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`
  } catch {
    return ''
  }
}

const queryClient = useQueryClient()
const isSyncing = ref(false)

async function handleSyncFacebook() {
  isSyncing.value = true
  try {
    const res = await syncFacebookMessages(props.account.id)
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: res.message || 'Mensajes sincronizados correctamente con Facebook.',
      position: 'bottom-right',
      timeout: 3000,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al sincronizar con Facebook',
      position: 'bottom-right',
      timeout: 4000,
    })
  } finally {
    isSyncing.value = false
  }
}

async function handleSyncInstagram() {
  isSyncing.value = true
  try {
    const res = await syncInstagramMessages(props.account.id)
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: res.message || 'Mensajes sincronizados correctamente con Instagram Direct.',
      position: 'bottom-right',
      timeout: 3000,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al sincronizar con Instagram',
      position: 'bottom-right',
      timeout: 4000,
    })
  } finally {
    isSyncing.value = false
  }
}

// Anti-Ban Guard Helpers
const antiBanColor = computed(() => {
  if (!props.account.anti_ban) return 'teal-4'
  switch (props.account.anti_ban.risk_level) {
    case 'danger':
      return 'negative'
    case 'warning':
      return 'amber-5'
    case 'safe':
    default:
      return 'teal-4'
  }
})

const showLimitDialog = ref(false)
const newDailyLimit = ref(props.account.daily_limit || props.account.anti_ban?.daily_limit || 100)
const isUpdatingLimit = ref(false)

function openEditLimitDialog() {
  newDailyLimit.value = props.account.daily_limit || props.account.anti_ban?.daily_limit || 100
  showLimitDialog.value = true
}

async function handleSaveLimit() {
  if (!newDailyLimit.value || newDailyLimit.value < 1) return
  isUpdatingLimit.value = true
  try {
    await updateWhatsAppDailyLimit(props.account.id, newDailyLimit.value)
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: 'Límite de seguridad Anti-Ban actualizado.',
      position: 'bottom-right',
      timeout: 2000,
    })
    showLimitDialog.value = false
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al actualizar el límite',
      position: 'bottom-right',
      timeout: 3000,
    })
  } finally {
    isUpdatingLimit.value = false
  }
}

// Edición de Datos del Canal
const showEditDialog = ref(false)
const isSavingEdit = ref(false)
const editForm = reactive({
  name: '',
  display_phone_number: '',
  daily_limit: 100,
  phone_number_id: '',
  business_account_id: '',
  access_token: '',
})

function openEditDialog() {
  editForm.name = props.account.name || ''
  editForm.display_phone_number = props.account.display_phone_number || ''
  editForm.daily_limit = props.account.daily_limit || props.account.anti_ban?.daily_limit || 100
  editForm.phone_number_id = props.account.phone_number_id || ''
  editForm.business_account_id = props.account.business_account_id || ''
  editForm.access_token = ''
  showEditDialog.value = true
}

async function handleSaveEdit() {
  if (!editForm.name || !editForm.name.trim()) {
    $q.notify({
      type: 'warning',
      message: 'El nombre del canal es obligatorio.',
      position: 'bottom-right',
    })
    return
  }

  isSavingEdit.value = true
  try {
    const payload: UpdateWhatsAppAccountPayload = {
      name: editForm.name.trim(),
      display_phone_number: editForm.display_phone_number?.trim() || null,
      daily_limit: editForm.daily_limit || 100,
    }

    if (props.account.session_type !== 'baileys_qr') {
      if (editForm.phone_number_id) payload.phone_number_id = editForm.phone_number_id.trim()
      if (editForm.business_account_id) payload.business_account_id = editForm.business_account_id.trim()
      if (editForm.access_token && editForm.access_token.trim()) {
        payload.access_token = editForm.access_token.trim()
      }
    }

    await updateWhatsAppAccountById(props.account.id, payload)
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })

    $q.notify({
      type: 'positive',
      message: 'Datos del canal actualizados exitosamente.',
      position: 'bottom-right',
      timeout: 2500,
    })
    showEditDialog.value = false
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al actualizar los datos del canal.',
      position: 'bottom-right',
      timeout: 3500,
    })
  } finally {
    isSavingEdit.value = false
  }
}

// Asignación de Operadores al Canal
const showAssignUsersDialog = ref(false)
const availableUsers = ref<Array<{ id: string; name: string; email: string; role: string }>>([])
const selectedUserIds = ref<string[]>([])
const isLoadingUsers = ref(false)
const isSavingUsers = ref(false)

async function openAssignUsersDialog() {
  selectedUserIds.value = props.account.authorized_users?.map(u => u.id) || []
  showAssignUsersDialog.value = true
  if (availableUsers.value.length === 0) {
    isLoadingUsers.value = true
    try {
      const res = await http.get('/users')
      availableUsers.value = res.data?.data || []
    } catch {
      $q.notify({ type: 'negative', message: 'No se pudieron cargar los operadores.' })
    } finally {
      isLoadingUsers.value = false
    }
  }
}

async function handleSaveAssignedUsers() {
  isSavingUsers.value = true
  try {
    const payload = selectedUserIds.value.map(uid => ({
      user_id: uid,
      can_view: true,
      can_reply: true,
    }))
    await assignUsersToWhatsAppAccount(props.account.id, payload)
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: 'Operadores asignados al canal exitosamente.',
      position: 'bottom-right',
      timeout: 2200,
    })
    showAssignUsersDialog.value = false
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al asignar operadores al canal',
      position: 'bottom-right',
      timeout: 3000,
    })
  } finally {
    isSavingUsers.value = false
  }
}
</script>

<style scoped lang="scss">
.anti-ban-section {
  background: rgba(0, 0, 0, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
}

.whatsapp-channel-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  transition: all var(--crm-transition-fast);

  &:hover {
    border-color: var(--crm-color-border-hover);
    transform: translateY(-1px);
  }
}

.channel-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--crm-color-border);

  &--connected {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.25);
  }
}

.platform-badge {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid;
  letter-spacing: 0.02em;
}

.channel-name {
  font-size: 0.92rem;
  letter-spacing: -0.01em;
}

.channel-number {
  font-family: var(--crm-font-mono, monospace);
  font-size: 0.75rem;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: var(--crm-radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  border: 1px solid transparent;

  &--connected {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    border-color: rgba(16, 185, 129, 0.2);

    .status-dot {
      background-color: var(--crm-color-primary);
    }
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.2);

    .status-dot {
      background-color: #f59e0b;
    }
  }

  &--disconnected {
    background: rgba(255, 255, 255, 0.04);
    color: var(--crm-color-muted);
    border-color: var(--crm-color-border);

    .status-dot {
      background-color: var(--crm-color-dim);
    }
  }
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.channel-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 8px !important;
}

.credentials-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.instruction-box {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: 6px;
  display: flex;
  align-items: flex-start;
}
</style>
