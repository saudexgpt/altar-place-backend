import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { usePlayerStore } from './player';

const track = (id, overrides = {}) => ({
  id,
  title: `Track ${id}`,
  stream_url: `http://example.test/stream/${id}`,
  ...overrides,
});

beforeEach(() => {
  setActivePinia(createPinia());
  delete window.__altarplaceAudio;
});

describe('player store', () => {
  it('playQueue loads the queue and starts at the given index', () => {
    const player = usePlayerStore();
    const tracks = [track(1), track(2), track(3)];

    player.playQueue(tracks, 1);

    expect(player.queue).toEqual(tracks);
    expect(player.currentIndex).toBe(1);
    expect(player.currentTrack).toEqual(track(2));
  });

  it('next() advances to the next track and stops at the end when repeat is off', () => {
    const player = usePlayerStore();
    player.playQueue([track(1), track(2)], 0);

    player.next();
    expect(player.currentIndex).toBe(1);

    player.next();
    expect(player.currentIndex).toBe(1); // stayed put — no track after the last one
  });

  it('next() wraps back to the start when repeat is "all"', () => {
    const player = usePlayerStore();
    player.playQueue([track(1), track(2)], 1);
    player.repeat = 'all';

    player.next();

    expect(player.currentIndex).toBe(0);
  });

  it('previous() moves back a track, or restarts the current one past 3 seconds in', () => {
    const player = usePlayerStore();
    player.playQueue([track(1), track(2)], 1);

    const audio = window.__altarplaceAudio;
    audio.currentTime = 10;

    player.previous();

    // More than 3s in: restarts the current track instead of going back.
    expect(player.currentIndex).toBe(1);
    expect(audio.currentTime).toBe(0);
  });

  it('previous() goes to the prior track when less than 3 seconds in', () => {
    const player = usePlayerStore();
    player.playQueue([track(1), track(2)], 1);

    const audio = window.__altarplaceAudio;
    audio.currentTime = 1;

    player.previous();

    expect(player.currentIndex).toBe(0);
  });

  it('hasNext/hasPrevious reflect queue position and repeat mode', () => {
    const player = usePlayerStore();
    player.playQueue([track(1), track(2)], 0);

    expect(player.hasPrevious).toBe(false);
    expect(player.hasNext).toBe(true);

    player.currentIndex = 1;
    expect(player.hasPrevious).toBe(true);
    expect(player.hasNext).toBe(false); // last track, repeat off

    player.repeat = 'all';
    expect(player.hasNext).toBe(true);
  });

  it('cycleRepeat rotates through off -> all -> one -> off', () => {
    const player = usePlayerStore();

    expect(player.repeat).toBe('off');
    player.cycleRepeat();
    expect(player.repeat).toBe('all');
    player.cycleRepeat();
    expect(player.repeat).toBe('one');
    player.cycleRepeat();
    expect(player.repeat).toBe('off');
  });

  it('toggleShuffle flips the shuffle flag', () => {
    const player = usePlayerStore();

    expect(player.shuffle).toBe(false);
    player.toggleShuffle();
    expect(player.shuffle).toBe(true);
  });

  it('setVolume updates both store state and the audio element', () => {
    const player = usePlayerStore();
    player.playQueue([track(1)], 0);

    player.setVolume(0.4);

    expect(player.volume).toBe(0.4);
    expect(window.__altarplaceAudio.volume).toBe(0.4);
  });
});
