import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    isWarping: false,
  }),

  actions: {
    triggerWarp(duration = 800) {
      if (this.isWarping) return;

      console.log('WARP DRIVE ENGAGED!');
      this.isWarping = true;

      setTimeout(() => {
        console.log('WARP DRIVE DISENGAGED.');
        this.isWarping = false;
      }, duration);
    }
  }
});