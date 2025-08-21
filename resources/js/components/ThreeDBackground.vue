<template>
  <div class="fixed top-0 left-0 w-full h-full" ref="canvasContainer"></div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useUiStore } from '../stores/uiStore';
import * as THREE from 'three';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';

const canvasContainer = ref(null);
let renderer, scene, camera, composer, animationFrameId;
const objects = [];
let particles, warpParticles;
let targetCameraZ = 50;
let mouse = new THREE.Vector2();

const uiStore = useUiStore();
const { isWarping } = storeToRefs(uiStore);

const onMouseMove = (event) => {
  mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
  mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
};

const onWindowScroll = () => {
  if (!isWarping.value) {
    targetCameraZ = 50 - window.scrollY * 0.05;
  }
};

onMounted(() => {
  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(window.devicePixelRatio);
  canvasContainer.value.appendChild(renderer.domElement);
  camera.position.z = targetCameraZ;

  const ambientLight = new THREE.AmbientLight(0xffffff, 1.5);
  scene.add(ambientLight);
  const pointLight1 = new THREE.PointLight(0x40e0d0, 50, 100);
  pointLight1.position.set(20, 30, 40);
  scene.add(pointLight1);
  const pointLight2 = new THREE.PointLight(0x9932cc, 50, 100);
  pointLight2.position.set(-20, -30, -40);
  scene.add(pointLight2);

  const geometries = [new THREE.IcosahedronGeometry(1.5, 0), new THREE.TorusGeometry(1, 0.4, 16, 100)];
  const material = new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.7, roughness: 0.2 });
  for (let i = 0; i < 100; i++) {
    const geometry = geometries[Math.floor(Math.random() * geometries.length)];
    const mesh = new THREE.Mesh(geometry, material);
    mesh.position.set((Math.random() - 0.5) * 100, (Math.random() - 0.5) * 100, (Math.random() - 0.5) * 100);
    mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
    const scale = Math.random() * 0.3 + 0.1;
    mesh.scale.set(scale, scale, scale);
    scene.add(mesh);
    objects.push(mesh);
  }

  const particlesGeometry = new THREE.BufferGeometry();
  const particlesCount = 5000;
  const positions = new Float32Array(particlesCount * 3);
  for (let i = 0; i < particlesCount; i++) {
    positions[i * 3 + 0] = (Math.random() - 0.5) * 100;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 100;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 100;
  }
  particlesGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  const particlesMaterial = new THREE.PointsMaterial({ color: 0xffffff, size: 0.1, blending: THREE.AdditiveBlending, transparent: true });
  particles = new THREE.Points(particlesGeometry, particlesMaterial);
  scene.add(particles);

  const warpParticlesGeometry = new THREE.BufferGeometry();
  const warpParticlesCount = 500;
  const warpPositions = new Float32Array(warpParticlesCount * 3);
  for (let i = 0; i < warpParticlesCount; i++) {
    warpPositions[i * 3 + 0] = (Math.random() - 0.5) * 50;
    warpPositions[i * 3 + 1] = (Math.random() - 0.5) * 50;
    warpPositions[i * 3 + 2] = (Math.random() - 0.5) * 100;
  }
  warpParticlesGeometry.setAttribute('position', new THREE.BufferAttribute(warpPositions, 3));
  const warpParticlesMaterial = new THREE.PointsMaterial({ color: 0x40e0d0, size: 0.3, blending: THREE.AdditiveBlending, transparent: true });
  warpParticles = new THREE.Points(warpParticlesGeometry, warpParticlesMaterial);
  warpParticles.visible = false;
  scene.add(warpParticles);

  const renderScene = new RenderPass(scene, camera);
  const bloomPass = new UnrealBloomPass(new THREE.Vector2(window.innerWidth, window.innerHeight), 1.0, 0.4, 0.85);
  composer = new EffectComposer(renderer);
  composer.addPass(renderScene);
  composer.addPass(bloomPass);

  const clock = new THREE.Clock();
  const animate = () => {
    animationFrameId = requestAnimationFrame(animate);
    const delta = clock.getDelta();
    
    if (isWarping.value) {
      particles.visible = false;
      warpParticles.visible = true;
      
      warpParticles.position.z += delta * 200;
      if (warpParticles.position.z > 100) {
        warpParticles.position.z = -100;
      }
      
      camera.fov += (120 - camera.fov) * 0.1;
      camera.updateProjectionMatrix();

    } else {
      particles.visible = true;
      warpParticles.visible = false;
      warpParticles.position.z = 0;

      camera.fov += (75 - camera.fov) * 0.1;
      camera.updateProjectionMatrix();

      particles.rotation.y += delta * 0.05;
      objects.forEach(obj => {
        obj.rotation.x += delta * 0.1;
        obj.rotation.y += delta * 0.1;
      });
      
      camera.position.x += (mouse.x * 5 - camera.position.x) * 0.05;
      camera.position.y += (mouse.y * 5 - camera.position.y) * 0.05;
      camera.position.z += (targetCameraZ - camera.position.z) * 0.1;
      camera.lookAt(scene.position);
    }

    composer.render();
  };
  
  animate();

  const onWindowResize = () => {
    const width = window.innerWidth;
    const height = window.innerHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    composer.setSize(width, height);
  };
  
  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('scroll', onWindowScroll);
  window.addEventListener('resize', onWindowResize);

  onUnmounted(() => {
    cancelAnimationFrame(animationFrameId);
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('scroll', onWindowScroll);
    window.removeEventListener('resize', onWindowResize);
  });
});
</script>