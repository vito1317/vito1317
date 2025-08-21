<template>
  <div class="loader-overlay">
    <div class="spinner">
      <div class="ring"></div>
      <div class="ring"></div>
      <div class="ring"></div>
      <div class="text">Loading...</div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  isLoading: Boolean,
});
</script>

<style scoped>
.loader-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: #111827; 
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999; 
  opacity: 1;
  transition: opacity 0.75s ease-in-out; 
}

.loader-overlay.loader-hidden {
  opacity: 0;
  pointer-events: none;
}

.spinner {
  width: 150px;
  height: 150px;
  position: relative;
  transform-style: preserve-3d;
  animation: spin 12s linear infinite;
}

@keyframes spin {
  from { transform: rotateY(0deg) rotateX(0deg); }
  to { transform: rotateY(360deg) rotateX(360deg); }
}

.text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #9ca3af;
  font-family: sans-serif;
  font-size: 1rem;
}

.ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: 2px solid transparent;
  border-radius: 50%;
}

.ring:nth-child(1) {
  border-bottom-color: #40e0d0; 
  transform: rotateY(35deg) rotateX(50deg);
}

.ring:nth-child(2) {
  border-right-color: #9932cc; 
  transform: rotateY(-55deg) rotateX(30deg);
}

.ring:nth-child(3) {
  border-top-color: #f0f0f0; 
  transform: rotateY(25deg) rotateX(-60deg);
}
</style>