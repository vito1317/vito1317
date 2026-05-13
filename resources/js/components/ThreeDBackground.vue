<template>
  <div class="fixed top-0 left-0 w-full h-full pointer-events-none" ref="canvasContainer"></div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useUiStore } from '../stores/uiStore';
import * as THREE from 'three';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { ShaderPass } from 'three/examples/jsm/postprocessing/ShaderPass.js';
import { FilmPass } from 'three/examples/jsm/postprocessing/FilmPass.js';

const canvasContainer = ref(null);
let renderer, scene, camera, composer, animationFrameId;
const objects = [];
let particles, warpParticles, grid, gridTop, dataBeams = [];
let ringGroup;
let targetCameraZ = 50;
const mouse = new THREE.Vector2();
const mouseTarget = new THREE.Vector2();

const uiStore = useUiStore();
const { isWarping } = storeToRefs(uiStore);

const onMouseMove = (event) => {
  mouseTarget.x = (event.clientX / window.innerWidth) * 2 - 1;
  mouseTarget.y = -(event.clientY / window.innerHeight) * 2 + 1;
};

const onWindowScroll = () => {
  if (!isWarping.value) {
    targetCameraZ = 50 - window.scrollY * 0.04;
  }
};

const isMobile = () => window.innerWidth < 768;

onMounted(() => {
  const mobile = isMobile();

  scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x05060f, 0.012);

  camera = new THREE.PerspectiveCamera(70, window.innerWidth / window.innerHeight, 0.1, 1000);
  renderer = new THREE.WebGLRenderer({ antialias: !mobile, alpha: true, powerPreference: 'high-performance' });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, mobile ? 1.5 : 2));
  renderer.setClearColor(0x000000, 0);
  canvasContainer.value.appendChild(renderer.domElement);
  camera.position.z = targetCameraZ;

  scene.add(new THREE.AmbientLight(0x404080, 0.8));
  const keyLight = new THREE.PointLight(0x22e0ff, 80, 160);
  keyLight.position.set(25, 35, 45);
  scene.add(keyLight);
  const rimLight = new THREE.PointLight(0xff2bd6, 80, 160);
  rimLight.position.set(-25, -30, -40);
  scene.add(rimLight);
  const fillLight = new THREE.PointLight(0x7b5bff, 40, 120);
  fillLight.position.set(0, 20, -30);
  scene.add(fillLight);

  const gridSize = 200;
  const gridDivisions = 60;
  grid = new THREE.GridHelper(gridSize, gridDivisions, 0x22e0ff, 0x1a4a66);
  grid.position.y = -30;
  grid.material.transparent = true;
  grid.material.opacity = 0.35;
  scene.add(grid);

  gridTop = new THREE.GridHelper(gridSize, gridDivisions, 0xff2bd6, 0x66204a);
  gridTop.position.y = 30;
  gridTop.rotation.x = Math.PI;
  gridTop.material.transparent = true;
  gridTop.material.opacity = 0.2;
  scene.add(gridTop);

  const wireGeometries = [
    new THREE.IcosahedronGeometry(1.6, 0),
    new THREE.TorusGeometry(1.2, 0.35, 12, 80),
    new THREE.OctahedronGeometry(1.4, 0),
    new THREE.TetrahedronGeometry(1.6, 0),
  ];
  const wireColors = [0x22e0ff, 0xff2bd6, 0x7b5bff, 0x5effa7];

  const wireCount = mobile ? 35 : 80;
  for (let i = 0; i < wireCount; i++) {
    const geo = wireGeometries[Math.floor(Math.random() * wireGeometries.length)];
    const color = wireColors[Math.floor(Math.random() * wireColors.length)];

    const coreMat = new THREE.MeshStandardMaterial({
      color,
      emissive: color,
      emissiveIntensity: 0.55,
      metalness: 0.85,
      roughness: 0.15,
      transparent: true,
      opacity: 0.28,
    });
    const core = new THREE.Mesh(geo, coreMat);

    const edges = new THREE.EdgesGeometry(geo);
    const edgeMat = new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.9 });
    const wire = new THREE.LineSegments(edges, edgeMat);
    core.add(wire);

    core.position.set(
      (Math.random() - 0.5) * 140,
      (Math.random() - 0.5) * 80,
      (Math.random() - 0.5) * 140,
    );
    core.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
    const scale = Math.random() * 0.6 + 0.2;
    core.scale.setScalar(scale);

    core.userData = {
      rotSpeed: new THREE.Vector3(
        (Math.random() - 0.5) * 0.4,
        (Math.random() - 0.5) * 0.4,
        (Math.random() - 0.5) * 0.3,
      ),
      floatOffset: Math.random() * Math.PI * 2,
      floatAmp: Math.random() * 0.5 + 0.2,
      baseY: core.position.y,
    };
    scene.add(core);
    objects.push(core);
  }

  ringGroup = new THREE.Group();
  for (let r = 0; r < 3; r++) {
    const ringGeo = new THREE.TorusGeometry(8 + r * 4, 0.04, 8, 128);
    const ringMat = new THREE.MeshBasicMaterial({
      color: r === 0 ? 0x22e0ff : r === 1 ? 0xff2bd6 : 0x7b5bff,
      transparent: true,
      opacity: 0.6,
    });
    const ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = Math.random() * Math.PI;
    ring.rotation.y = Math.random() * Math.PI;
    ring.userData = {
      rotSpeed: new THREE.Vector3(
        (Math.random() - 0.5) * 0.15,
        (Math.random() - 0.5) * 0.15,
        (Math.random() - 0.5) * 0.1,
      ),
    };
    ringGroup.add(ring);
  }
  ringGroup.position.set(0, 0, -20);
  scene.add(ringGroup);

  for (let b = 0; b < 6; b++) {
    const beamGeo = new THREE.CylinderGeometry(0.08, 0.08, 60, 8, 1, true);
    const beamMat = new THREE.MeshBasicMaterial({
      color: b % 2 === 0 ? 0x22e0ff : 0xff2bd6,
      transparent: true,
      opacity: 0.35,
      blending: THREE.AdditiveBlending,
      side: THREE.DoubleSide,
    });
    const beam = new THREE.Mesh(beamGeo, beamMat);
    beam.position.set(
      (Math.random() - 0.5) * 80,
      0,
      (Math.random() - 0.5) * 80,
    );
    beam.userData = { speed: Math.random() * 0.6 + 0.3, basePosY: 0 };
    scene.add(beam);
    dataBeams.push(beam);
  }

  const particlesGeometry = new THREE.BufferGeometry();
  const particlesCount = mobile ? 1500 : 4000;
  const positions = new Float32Array(particlesCount * 3);
  const colors = new Float32Array(particlesCount * 3);
  const palette = [
    new THREE.Color(0x22e0ff),
    new THREE.Color(0xff2bd6),
    new THREE.Color(0x7b5bff),
    new THREE.Color(0x5effa7),
    new THREE.Color(0xffffff),
  ];
  for (let i = 0; i < particlesCount; i++) {
    positions[i * 3 + 0] = (Math.random() - 0.5) * 160;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 100;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 160;
    const c = palette[Math.floor(Math.random() * palette.length)];
    colors[i * 3 + 0] = c.r;
    colors[i * 3 + 1] = c.g;
    colors[i * 3 + 2] = c.b;
  }
  particlesGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  particlesGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
  const particlesMaterial = new THREE.PointsMaterial({
    size: 0.14,
    vertexColors: true,
    blending: THREE.AdditiveBlending,
    transparent: true,
    depthWrite: false,
  });
  particles = new THREE.Points(particlesGeometry, particlesMaterial);
  scene.add(particles);

  const warpParticlesGeometry = new THREE.BufferGeometry();
  const warpParticlesCount = 800;
  const warpPositions = new Float32Array(warpParticlesCount * 3);
  for (let i = 0; i < warpParticlesCount; i++) {
    warpPositions[i * 3 + 0] = (Math.random() - 0.5) * 50;
    warpPositions[i * 3 + 1] = (Math.random() - 0.5) * 50;
    warpPositions[i * 3 + 2] = (Math.random() - 0.5) * 100;
  }
  warpParticlesGeometry.setAttribute('position', new THREE.BufferAttribute(warpPositions, 3));
  const warpParticlesMaterial = new THREE.PointsMaterial({
    color: 0x22e0ff,
    size: 0.35,
    blending: THREE.AdditiveBlending,
    transparent: true,
    depthWrite: false,
  });
  warpParticles = new THREE.Points(warpParticlesGeometry, warpParticlesMaterial);
  warpParticles.visible = false;
  scene.add(warpParticles);

  const renderScene = new RenderPass(scene, camera);
  const bloomPass = new UnrealBloomPass(
    new THREE.Vector2(window.innerWidth, window.innerHeight),
    1.25,
    0.55,
    0.78,
  );
  const filmPass = new FilmPass(0.22, 0.08, 600, false);

  const chromaShader = {
    uniforms: {
      tDiffuse: { value: null },
      amount: { value: 0.0018 },
    },
    vertexShader: `
      varying vec2 vUv;
      void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }
    `,
    fragmentShader: `
      uniform sampler2D tDiffuse;
      uniform float amount;
      varying vec2 vUv;
      void main() {
        vec2 offset = (vUv - 0.5) * amount;
        float r = texture2D(tDiffuse, vUv + offset).r;
        float g = texture2D(tDiffuse, vUv).g;
        float b = texture2D(tDiffuse, vUv - offset).b;
        gl_FragColor = vec4(r, g, b, 1.0);
      }
    `,
  };
  const chromaPass = new ShaderPass(chromaShader);

  composer = new EffectComposer(renderer);
  composer.addPass(renderScene);
  composer.addPass(bloomPass);
  composer.addPass(chromaPass);
  composer.addPass(filmPass);

  const clock = new THREE.Clock();
  let elapsed = 0;

  const animate = () => {
    animationFrameId = requestAnimationFrame(animate);
    const delta = clock.getDelta();
    elapsed += delta;

    mouse.x += (mouseTarget.x - mouse.x) * 0.05;
    mouse.y += (mouseTarget.y - mouse.y) * 0.05;

    if (isWarping.value) {
      particles.visible = false;
      warpParticles.visible = true;
      grid.visible = false;
      gridTop.visible = false;

      warpParticles.position.z += delta * 220;
      if (warpParticles.position.z > 100) warpParticles.position.z = -100;

      chromaPass.uniforms.amount.value += (0.012 - chromaPass.uniforms.amount.value) * 0.15;
      camera.fov += (120 - camera.fov) * 0.1;
      camera.updateProjectionMatrix();
    } else {
      particles.visible = true;
      warpParticles.visible = false;
      warpParticles.position.z = 0;
      grid.visible = true;
      gridTop.visible = true;

      chromaPass.uniforms.amount.value += (0.0018 - chromaPass.uniforms.amount.value) * 0.1;
      camera.fov += (70 - camera.fov) * 0.1;
      camera.updateProjectionMatrix();

      particles.rotation.y += delta * 0.04;
      particles.rotation.x += delta * 0.01;

      objects.forEach((obj) => {
        const ud = obj.userData;
        obj.rotation.x += delta * ud.rotSpeed.x;
        obj.rotation.y += delta * ud.rotSpeed.y;
        obj.rotation.z += delta * ud.rotSpeed.z;
        obj.position.y = ud.baseY + Math.sin(elapsed * 0.8 + ud.floatOffset) * ud.floatAmp;
      });

      ringGroup.children.forEach((ring) => {
        const ud = ring.userData;
        ring.rotation.x += delta * ud.rotSpeed.x;
        ring.rotation.y += delta * ud.rotSpeed.y;
        ring.rotation.z += delta * ud.rotSpeed.z;
      });
      ringGroup.position.x = Math.sin(elapsed * 0.2) * 5;
      ringGroup.position.y = Math.cos(elapsed * 0.25) * 3;

      dataBeams.forEach((beam) => {
        beam.position.y = ((elapsed * beam.userData.speed * 30) % 120) - 60;
        beam.material.opacity = 0.25 + Math.sin(elapsed * 3 + beam.position.x) * 0.15;
      });

      grid.position.z = (elapsed * 4) % 6.66 - 3.33;
      gridTop.position.z = -((elapsed * 4) % 6.66 - 3.33);

      camera.position.x += (mouse.x * 6 - camera.position.x) * 0.04;
      camera.position.y += (mouse.y * 4 - camera.position.y) * 0.04;
      camera.position.z += (targetCameraZ - camera.position.z) * 0.08;
      camera.lookAt(scene.position);
    }

    composer.render();
  };

  animate();

  const onWindowResize = () => {
    const width = window.innerWidth;
    const height = window.innerHeight;
    const nowMobile = isMobile();
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, nowMobile ? 1.5 : 2));
    composer.setSize(width, height);
  };

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('scroll', onWindowScroll, { passive: true });
  window.addEventListener('resize', onWindowResize);

  onUnmounted(() => {
    cancelAnimationFrame(animationFrameId);
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('scroll', onWindowScroll);
    window.removeEventListener('resize', onWindowResize);
    renderer.dispose();
    composer.dispose();
  });
});
</script>
