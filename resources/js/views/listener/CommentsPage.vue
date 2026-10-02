<template>
  <div class="max-w-2xl">
    <div class="flex items-center justify-between mb-2">
      <h1 class="text-2xl font-heading font-semibold">Comments</h1>
      <button v-if="track" type="button" class="text-ink-muted hover:text-danger" aria-label="Report this track" @click="reportDialog = { type: 'track', id: track.id, label: 'Report this track?' }">
        <Flag :size="18" />
      </button>
    </div>
    <p v-if="track" class="text-sm text-ink-muted mb-6">On "{{ track.title }}"</p>

    <LoadError v-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-for="comment in comments" :key="comment.id" class="flex items-start gap-3 py-3 border-b border-navy-500/20 last:border-0">
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-navy-400 to-gold flex items-center justify-center text-xs font-semibold shrink-0 overflow-hidden">
          <img v-if="comment.user.avatar_url" :src="comment.user.avatar_url" class="w-full h-full object-cover" alt="" />
          <span v-else>{{ initialsFor(comment.user.name) }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="flex items-baseline gap-2">
            <span class="text-sm font-semibold">{{ comment.user.name }}</span>
            <span class="text-xs text-ink-muted">{{ relativeTime(comment.created_at) }}</span>
          </p>
          <p class="text-sm mt-0.5">{{ comment.body }}</p>
        </div>
        <button
          v-if="comment.is_owner"
          type="button"
          class="text-ink-muted hover:text-danger shrink-0"
          aria-label="Delete comment"
          @click="deletingComment = comment"
        >
          <Trash2 :size="16" />
        </button>
        <button
          v-else
          type="button"
          class="text-ink-muted hover:text-danger shrink-0"
          aria-label="Report comment"
          @click="reportDialog = { type: 'comment', id: comment.id, label: 'Report this comment?' }"
        >
          <Flag :size="16" />
        </button>
      </div>

      <p v-if="!comments.length" class="text-sm text-ink-muted py-10 text-center">Be the first to comment on this track.</p>
    </template>

    <form class="flex items-center gap-2 mt-6" @submit.prevent="submit">
      <input
        v-model="draft"
        type="text"
        placeholder="Add a comment…"
        class="flex-1 bg-navy-800 border border-navy-500/60 rounded-full px-4 py-2.5 text-sm placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-gold/50"
      />
      <button type="submit" :disabled="!draft.trim() || isSubmitting" class="text-sm font-semibold bg-gold text-navy-950 rounded-full px-5 py-2.5 hover:bg-gold-tint transition-colors disabled:opacity-50">
        Post
      </button>
    </form>

    <ConfirmDialog
      v-if="deletingComment"
      title="Delete comment?"
      confirm-label="Delete"
      :danger="true"
      @confirm="remove(deletingComment)"
      @cancel="deletingComment = null"
    />

    <div v-if="reportDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="reportDialog = null">
      <div class="w-full max-w-sm bg-navy-800 border border-navy-500/60 rounded-2xl p-6">
        <h3 class="font-heading font-semibold text-lg mb-4">{{ reportDialog.label }}</h3>
        <div class="space-y-2 mb-4">
          <label v-for="option in reportReasons" :key="option.value" class="flex items-center gap-2 text-sm">
            <input v-model="reportReason" type="radio" :value="option.value" />
            {{ option.label }}
          </label>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" class="text-sm text-ink-muted hover:text-white px-4 py-2" @click="reportDialog = null">Cancel</button>
          <button type="button" class="text-sm font-semibold bg-danger text-white rounded-full px-4 py-2" @click="submitReport">Report</button>
        </div>
      </div>
    </div>

    <p v-if="reportConfirmation" class="fixed bottom-6 left-1/2 -translate-x-1/2 text-sm bg-navy-800 border border-navy-500/60 rounded-full px-4 py-2 shadow-xl">
      {{ reportConfirmation }}
    </p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { Flag, Trash2 } from '@lucide/vue';
import { commentsApi } from '@/services/commentsApi';
import { catalogApi } from '@/services/catalogApi';
import { reportsApi } from '@/services/reportsApi';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import LoadError from '@/components/ui/LoadError.vue';

const route = useRoute();
const trackId = route.params.id;

const track = ref(null);
const comments = ref([]);
const draft = ref('');
const isSubmitting = ref(false);
const loadError = ref('');
const deletingComment = ref(null);
const reportDialog = ref(null);
const reportReason = ref('spam');
const reportConfirmation = ref('');

const reportReasons = [
  { value: 'spam', label: 'Spam' },
  { value: 'abuse', label: 'Abusive or harmful' },
  { value: 'copyright', label: 'Copyright violation' },
  { value: 'inappropriate', label: 'Inappropriate content' },
  { value: 'other', label: 'Other' },
];

function initialsFor(name) {
  return (name ?? '').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
}

function relativeTime(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
}

async function submit() {
  const body = draft.value.trim();
  if (!body) return;

  isSubmitting.value = true;
  try {
    const comment = await commentsApi.create(trackId, body);
    comments.value.unshift(comment);
    draft.value = '';
  } finally {
    isSubmitting.value = false;
  }
}

async function remove(comment) {
  await commentsApi.destroy(comment.id);
  comments.value = comments.value.filter((c) => c.id !== comment.id);
  deletingComment.value = null;
}

async function submitReport() {
  const { type, id } = reportDialog.value;
  await reportsApi.report(type, id, reportReason.value);
  reportDialog.value = null;
  reportConfirmation.value = `Thanks — we'll review this ${type}.`;
  setTimeout(() => { reportConfirmation.value = ''; }, 2500);
}

async function load() {
  loadError.value = '';
  try {
    const [trackRes, commentsRes] = await Promise.all([
      catalogApi.track(trackId),
      commentsApi.list(trackId),
    ]);
    track.value = trackRes;
    comments.value = commentsRes;
  } catch {
    loadError.value = 'Could not load comments. Check your connection and try again.';
  }
}

onMounted(load);
</script>
