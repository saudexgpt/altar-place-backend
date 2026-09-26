import { defineStore } from 'pinia';

export const getSharedAudioElement = () => {
  if (!window.__altarplaceAudio) {
    window.__altarplaceAudio = new Audio();
  }

  return window.__altarplaceAudio;
};

export const usePlayerStore = defineStore('player', {
  state: () => ({
    queue: [],
    currentIndex: -1,
    isPlaying: false,
    currentTime: 0,
    duration: 0,
    volume: 1,
    shuffle: false,
    repeat: 'off', // 'off' | 'all' | 'one'
    isBound: false,
  }),

  getters: {
    currentTrack: (state) => state.queue[state.currentIndex] ?? null,
    hasNext: (state) => state.repeat !== 'off' || state.currentIndex < state.queue.length - 1,
    hasPrevious: (state) => state.currentIndex > 0,
  },

  actions: {
    bindAudioElement() {
      if (this.isBound) return;
      this.isBound = true;

      const audio = getSharedAudioElement();

      audio.addEventListener('timeupdate', () => {
        this.currentTime = audio.currentTime;
      });
      audio.addEventListener('loadedmetadata', () => {
        this.duration = audio.duration || 0;
      });
      audio.addEventListener('play', () => {
        this.isPlaying = true;
      });
      audio.addEventListener('pause', () => {
        this.isPlaying = false;
      });
      audio.addEventListener('ended', () => {
        if (this.repeat === 'one') {
          audio.currentTime = 0;
          audio.play();
          return;
        }

        this.next();
      });
    },

    playQueue(tracks, startIndex = 0) {
      this.bindAudioElement();

      this.queue = tracks;
      this.currentIndex = startIndex;
      this.loadCurrentTrack();
    },

    loadCurrentTrack() {
      const track = this.currentTrack;
      if (!track) return;

      const audio = getSharedAudioElement();
      audio.src = track.stream_url;
      audio.volume = this.volume;
      audio.play().catch(() => {
        // Autoplay can be blocked before the first user gesture — the mini
        // player still shows the loaded track, just paused.
      });
    },

    togglePlay() {
      const audio = getSharedAudioElement();

      if (!this.currentTrack) return;

      if (this.isPlaying) {
        audio.pause();
      } else {
        audio.play().catch(() => {});
      }
    },

    next() {
      if (!this.queue.length) return;

      if (this.shuffle) {
        this.currentIndex = Math.floor(Math.random() * this.queue.length);
        this.loadCurrentTrack();
        return;
      }

      if (this.currentIndex < this.queue.length - 1) {
        this.currentIndex += 1;
        this.loadCurrentTrack();
      } else if (this.repeat === 'all') {
        this.currentIndex = 0;
        this.loadCurrentTrack();
      }
    },

    previous() {
      if (!this.queue.length) return;

      const audio = getSharedAudioElement();

      // Restart the current track if we're more than 3s in, like most players.
      if (audio.currentTime > 3) {
        audio.currentTime = 0;
        return;
      }

      if (this.currentIndex > 0) {
        this.currentIndex -= 1;
        this.loadCurrentTrack();
      }
    },

    seek(time) {
      const audio = getSharedAudioElement();
      audio.currentTime = time;
      this.currentTime = time;
    },

    setVolume(value) {
      this.volume = value;
      getSharedAudioElement().volume = value;
    },

    toggleShuffle() {
      this.shuffle = !this.shuffle;
    },

    cycleRepeat() {
      this.repeat = { off: 'all', all: 'one', one: 'off' }[this.repeat];
    },
  },
});
