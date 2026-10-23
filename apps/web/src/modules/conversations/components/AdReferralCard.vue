<template>
  <div
    v-if="referral"
    class="ad-referral-card"
    :class="[
      isAd ? 'ad-referral-card--ad' : 'ad-referral-card--post',
      compact ? 'ad-referral-card--compact' : ''
    ]"
  >
    <!-- Header de Procedencia -->
    <div class="ad-referral-header row items-center justify-between no-wrap q-px-sm q-py-xs">
      <div class="row items-center q-gutter-x-xs ellipsis">
        <q-icon
          :name="isAd ? 'sym_r_campaign' : 'sym_r_feed'"
          size="16px"
          :class="isAd ? 'text-deep-purple-3' : 'text-teal-3'"
        />
        <span class="text-caption text-weight-bolder ellipsis" :class="isAd ? 'text-deep-purple-2' : 'text-teal-2'">
          {{ isAd ? 'Anuncio Patrocinado (Meta Ads)' : 'Publicación de Fanpage' }}
        </span>
      </div>

      <q-badge
        v-if="referral.source_id"
        dense
        color="dark"
        text-color="grey-4"
        class="font-mono text-caption q-px-xs"
        style="font-size: 0.65rem;"
      >
        ID: {{ String(referral.source_id).slice(-8) }}
      </q-badge>
    </div>

    <!-- Contenido Principal: Miniatura + Titular + Copy -->
    <div class="ad-referral-body row no-wrap items-start q-pa-sm q-gutter-x-sm">
      <!-- Miniatura / Imagen -->
      <div
        v-if="referralImage"
        class="ad-referral-thumb-wrap relative-position cursor-pointer flex-shrink-0"
        @click="openImage(referralImage)"
      >
        <img
          :src="referralImage"
          alt="Anuncio"
          class="ad-referral-thumb rounded-borders"
          loading="lazy"
        />
        <div v-if="referral.media_type === 'video'" class="ad-referral-play-icon">
          <q-icon name="sym_r_play_arrow" size="18px" color="white" />
        </div>
      </div>

      <!-- Textos -->
      <div class="column col min-width-0">
        <div v-if="referral.headline" class="ad-referral-title text-weight-bold text-white ellipsis-2-lines">
          {{ referral.headline }}
        </div>
        <div v-if="referral.body" class="ad-referral-snippet text-caption text-grey-3 q-mt-xs ellipsis-3-lines">
          {{ referral.body }}
        </div>

        <!-- Enlace de Meta / Facebook -->
        <div v-if="referral.source_url" class="q-mt-xs">
          <a
            :href="referral.source_url"
            target="_blank"
            rel="noopener noreferrer"
            class="ad-referral-link row items-center q-gutter-x-xs"
            @click.stop
          >
            <q-icon name="sym_r_open_in_new" size="13px" />
            <span class="text-caption text-weight-medium">Ver en Facebook / Instagram</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AdReferralMetadata } from '@/modules/conversations/types/conversation.types'

const props = withDefaults(
  defineProps<{
    referral?: AdReferralMetadata | null
    compact?: boolean
  }>(),
  {
    referral: null,
    compact: false,
  }
)

const isAd = computed(() => {
  const type = String(props.referral?.source_type || 'ad').toLowerCase()
  return type === 'ad' || type === 'ads'
})

const referralImage = computed(() => {
  return props.referral?.image_url || props.referral?.thumbnail_base64 || null
})

function openImage(url: string) {
  if (url) {
    window.open(url, '_blank')
  }
}
</script>

<style scoped>
.ad-referral-card {
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(18, 22, 33, 0.85);
  margin-bottom: 8px;
  max-width: 420px;
  width: 100%;
  backdrop-filter: blur(8px);
}

.ad-referral-card--ad {
  border-color: rgba(147, 51, 234, 0.4);
  background: linear-gradient(135deg, rgba(88, 28, 135, 0.25) 0%, rgba(15, 23, 42, 0.9) 100%);
}

.ad-referral-card--post {
  border-color: rgba(20, 184, 166, 0.4);
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.2) 0%, rgba(15, 23, 42, 0.9) 100%);
}

.ad-referral-header {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(0, 0, 0, 0.25);
}

.ad-referral-card--ad .ad-referral-header {
  border-bottom-color: rgba(168, 85, 247, 0.2);
}

.ad-referral-card--post .ad-referral-header {
  border-bottom-color: rgba(45, 212, 191, 0.2);
}

.ad-referral-thumb-wrap {
  width: 64px;
  height: 64px;
  border-radius: 8px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ad-referral-card--compact .ad-referral-thumb-wrap {
  width: 52px;
  height: 52px;
}

.ad-referral-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s ease;
}

.ad-referral-thumb-wrap:hover .ad-referral-thumb {
  transform: scale(1.05);
}

.ad-referral-play-icon {
  position: absolute;
  background: rgba(0, 0, 0, 0.55);
  border-radius: 50%;
  padding: 3px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ad-referral-title {
  font-size: 0.85rem;
  line-height: 1.25;
}

.ad-referral-snippet {
  font-size: 0.75rem;
  line-height: 1.35;
}

.ad-referral-link {
  color: #60a5fa;
  text-decoration: none;
  font-size: 0.72rem;
  display: inline-flex;
  transition: color 0.15s ease;
}

.ad-referral-link:hover {
  color: #93c5fd;
  text-decoration: underline;
}

.flex-shrink-0 {
  flex-shrink: 0;
}
</style>
