import { onMounted, onUnmounted } from 'vue';

/**
 * Standard modal keyboard behavior: Escape closes it, and focus moves into
 * the dialog on open so keyboard/screen-reader users aren't left focused on
 * a button that's now hidden behind the overlay.
 *
 * @param {() => void} onClose
 * @param {import('vue').Ref<HTMLElement | null> | null} [autofocusTarget]
 */
export function useModalA11y(onClose, autofocusTarget = null) {
  function handleKeydown(event) {
    if (event.key === 'Escape') {
      onClose();
    }
  }

  onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    // Let the modal's own content render before trying to focus into it.
    requestAnimationFrame(() => autofocusTarget?.value?.focus());
  });

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
  });
}
