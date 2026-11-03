import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    isWarping: false,
    // AI 對話框是否開著（手機上用來暫停被蓋住的背景動畫）
    aiPanelOpen: false,
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