import { defineStore } from 'pinia';
import axios from 'axios';

export const useProjectStore = defineStore('projectStore', {
  state: () => ({
    projects: [],
    loaded: false,
  }),
  actions: {
    async fetchProjects() {
      if (this.loaded) return;
      try {
        const res = await axios.get('/api/projects');
        this.projects = res.data;
        this.loaded = true;
      } catch (err) {
        console.error('[fetchProjects] 載入失敗:', err);
        this.projects = [];
        this.loaded = false;
      }
    },
  },
});
