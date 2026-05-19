<template>
    <div class="rover-container" :style="{ width: size + 'px' }">
        <svg :width="size" :height="size * 0.75" viewBox="0 0 200 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Ground / Surface -->
            <ellipse cx="100" cy="135" rx="90" ry="6" fill="#F8EDE0" opacity="0.6" class="rover-shadow" />

            <!-- Dust particles -->
            <circle cx="30" cy="130" r="1.5" fill="#FFD0D6" opacity="0.4" class="dust dust-1" />
            <circle cx="50" cy="128" r="1" fill="#FFD0D6" opacity="0.3" class="dust dust-2" />
            <circle cx="160" cy="131" r="1.2" fill="#FFD0D6" opacity="0.35" class="dust dust-3" />
            <circle cx="170" cy="127" r="1" fill="#FFD0D6" opacity="0.3" class="dust dust-4" />

            <!-- Body structure -->
            <g class="rover-body">
                <!-- Suspension bars -->
                <line x1="65" y1="105" x2="55" y2="120" stroke="#8B5E6B" stroke-width="3" stroke-linecap="round" />
                <line x1="135" y1="105" x2="145" y2="120" stroke="#8B5E6B" stroke-width="3" stroke-linecap="round" />
                <line x1="85" y1="108" x2="80" y2="120" stroke="#8B5E6B" stroke-width="2.5" stroke-linecap="round" />
                <line x1="115" y1="108" x2="120" y2="120" stroke="#8B5E6B" stroke-width="2.5" stroke-linecap="round" />

                <!-- Main body -->
                <rect x="55" y="70" rx="12" ry="12" width="90" height="40" fill="url(#bodyGradient)" stroke="#E8637A" stroke-width="1.5" />

                <!-- Body detail lines -->
                <line x1="62" y1="85" x2="88" y2="85" stroke="#C49A4A" stroke-width="0.8" opacity="0.5" />
                <line x1="112" y1="85" x2="138" y2="85" stroke="#C49A4A" stroke-width="0.8" opacity="0.5" />

                <!-- Panel lights -->
                <circle cx="70" cy="78" r="2.5" fill="#4ADE80" class="light light-1" />
                <circle cx="80" cy="78" r="2.5" fill="#FCD34D" class="light light-2" />
                <circle cx="90" cy="78" r="2.5" fill="#4ADE80" class="light light-3" />

                <!-- Solar panel left -->
                <rect x="20" y="60" rx="3" ry="3" width="35" height="20" fill="url(#panelGradient)" stroke="#8B5E6B" stroke-width="1" />
                <line x1="27" y1="60" x2="27" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="37" y1="60" x2="37" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="47" y1="60" x2="47" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="20" y1="70" x2="55" y2="70" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <!-- Panel arm -->
                <rect x="50" y="73" rx="1" ry="1" width="10" height="4" fill="#8B5E6B" />

                <!-- Solar panel right -->
                <rect x="145" y="60" rx="3" ry="3" width="35" height="20" fill="url(#panelGradient)" stroke="#8B5E6B" stroke-width="1" />
                <line x1="152" y1="60" x2="152" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="162" y1="60" x2="162" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="172" y1="60" x2="172" y2="80" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <line x1="145" y1="70" x2="180" y2="70" stroke="#7A5C68" stroke-width="0.5" opacity="0.5" />
                <!-- Panel arm -->
                <rect x="140" y="73" rx="1" ry="1" width="10" height="4" fill="#8B5E6B" />

                <!-- Head / Camera -->
                <rect x="82" y="45" rx="8" ry="8" width="36" height="28" fill="url(#headGradient)" stroke="#E8637A" stroke-width="1.5" />

                <!-- Neck -->
                <rect x="95" y="68" rx="2" ry="2" width="10" height="6" fill="#8B5E6B" />

                <!-- Eyes -->
                <g class="eyes">
                    <circle cx="93" cy="57" r="5" fill="white" stroke="#8B5E6B" stroke-width="1" />
                    <circle cx="107" cy="57" r="5" fill="white" stroke="#8B5E6B" stroke-width="1" />
                    <circle cx="94" cy="57" r="2.5" fill="#4E2844" class="pupil pupil-left" />
                    <circle cx="108" cy="57" r="2.5" fill="#4E2844" class="pupil pupil-right" />
                    <!-- Eye shine -->
                    <circle cx="95" cy="55.5" r="1" fill="white" opacity="0.8" />
                    <circle cx="109" cy="55.5" r="1" fill="white" opacity="0.8" />
                </g>

                <!-- Eyelid for blink -->
                <rect x="86" y="50" width="14" height="0" fill="#D4A0AA" rx="2" class="eyelid eyelid-left" />
                <rect x="100" y="50" width="14" height="0" fill="#D4A0AA" rx="2" class="eyelid eyelid-right" />

                <!-- Antenna -->
                <g class="antenna">
                    <line x1="100" y1="45" x2="100" y2="25" stroke="#8B5E6B" stroke-width="2" stroke-linecap="round" />
                    <circle cx="100" cy="22" r="4" fill="#E8637A" class="antenna-tip" />
                    <circle cx="100" cy="22" r="2" fill="#FF6B8A" opacity="0.8" class="antenna-glow" />
                </g>

                <!-- Smile -->
                <path d="M94 63 Q100 67 106 63" stroke="#8B5E6B" stroke-width="1.2" fill="none" stroke-linecap="round" class="smile" />
            </g>

            <!-- Wheels -->
            <g class="wheels">
                <!-- Left wheel -->
                <circle cx="55" cy="128" r="10" fill="url(#wheelGradient)" stroke="#4E2844" stroke-width="2" class="wheel wheel-left" />
                <circle cx="55" cy="128" r="4" fill="#E8637A" />
                <line x1="55" y1="118" x2="55" y2="138" stroke="#4E2844" stroke-width="1" class="wheel-spoke spoke-1-l" />
                <line x1="45" y1="128" x2="65" y2="128" stroke="#4E2844" stroke-width="1" class="wheel-spoke spoke-2-l" />

                <!-- Center-left wheel -->
                <circle cx="80" cy="128" r="8" fill="url(#wheelGradient)" stroke="#4E2844" stroke-width="1.5" class="wheel wheel-cl" />
                <circle cx="80" cy="128" r="3" fill="#E8637A" />

                <!-- Center-right wheel -->
                <circle cx="120" cy="128" r="8" fill="url(#wheelGradient)" stroke="#4E2844" stroke-width="1.5" class="wheel wheel-cr" />
                <circle cx="120" cy="128" r="3" fill="#E8637A" />

                <!-- Right wheel -->
                <circle cx="145" cy="128" r="10" fill="url(#wheelGradient)" stroke="#4E2844" stroke-width="2" class="wheel wheel-right" />
                <circle cx="145" cy="128" r="4" fill="#E8637A" />
                <line x1="145" y1="118" x2="145" y2="138" stroke="#4E2844" stroke-width="1" class="wheel-spoke spoke-1-r" />
                <line x1="135" y1="128" x2="155" y2="128" stroke="#4E2844" stroke-width="1" class="wheel-spoke spoke-2-r" />
            </g>

            <!-- Signal waves from antenna -->
            <g class="signal-waves" opacity="0.4">
                <path d="M108 18 Q112 14 108 10" stroke="#E8637A" stroke-width="1" fill="none" class="wave wave-1" />
                <path d="M111 20 Q117 14 111 8" stroke="#E8637A" stroke-width="0.8" fill="none" class="wave wave-2" />
                <path d="M114 22 Q122 14 114 6" stroke="#E8637A" stroke-width="0.6" fill="none" class="wave wave-3" />
            </g>

            <!-- Gradients -->
            <defs>
                <linearGradient id="bodyGradient" x1="55" y1="70" x2="145" y2="110">
                    <stop offset="0%" stop-color="#FFE8EB" />
                    <stop offset="50%" stop-color="#FFD0D6" />
                    <stop offset="100%" stop-color="#FFD0D6" />
                </linearGradient>
                <linearGradient id="headGradient" x1="82" y1="45" x2="118" y2="73">
                    <stop offset="0%" stop-color="#FFE8EB" />
                    <stop offset="100%" stop-color="#FFD0D6" />
                </linearGradient>
                <linearGradient id="panelGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#4A6FA5" />
                    <stop offset="100%" stop-color="#2D4A7A" />
                </linearGradient>
                <radialGradient id="wheelGradient" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#F4899A" />
                    <stop offset="100%" stop-color="#B03550" />
                </radialGradient>
            </defs>
        </svg>
    </div>
</template>

<script setup>
defineProps({
    size: {
        type: Number,
        default: 200,
    },
});
</script>

<style scoped>
.rover-container {
    display: inline-block;
}

/* Rover body bobbing */
.rover-body {
    animation: bodyBob 3s ease-in-out infinite;
    transform-origin: center 100px;
}

@keyframes bodyBob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

/* Wheel rotation */
.wheel {
    animation: wheelSpin 2s linear infinite;
}

.wheel-left, .wheel-right {
    transform-origin: center;
}

@keyframes wheelSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.wheel-left { animation: wheelSpinLeft 2s linear infinite; transform-origin: 55px 128px; }
.wheel-cl   { animation: wheelSpinCL 2.5s linear infinite; transform-origin: 80px 128px; }
.wheel-cr   { animation: wheelSpinCR 2.5s linear infinite; transform-origin: 120px 128px; }
.wheel-right { animation: wheelSpinRight 2s linear infinite; transform-origin: 145px 128px; }

@keyframes wheelSpinLeft  { to { transform: rotate(360deg); } }
@keyframes wheelSpinCL    { to { transform: rotate(360deg); } }
@keyframes wheelSpinCR    { to { transform: rotate(360deg); } }
@keyframes wheelSpinRight { to { transform: rotate(360deg); } }

/* Spokes rotate with wheels */
.spoke-1-l, .spoke-2-l { animation: wheelSpinLeft 2s linear infinite; transform-origin: 55px 128px; }
.spoke-1-r, .spoke-2-r { animation: wheelSpinRight 2s linear infinite; transform-origin: 145px 128px; }

/* Antenna wobble */
.antenna {
    animation: antennaWobble 4s ease-in-out infinite;
    transform-origin: 100px 45px;
}

@keyframes antennaWobble {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(3deg); }
    75% { transform: rotate(-3deg); }
}

/* Antenna tip glow */
.antenna-glow {
    animation: glow 1.5s ease-in-out infinite;
}

@keyframes glow {
    0%, 100% { opacity: 0.4; r: 2; }
    50% { opacity: 1; r: 3.5; }
}

.antenna-tip {
    animation: tipPulse 1.5s ease-in-out infinite;
}

@keyframes tipPulse {
    0%, 100% { fill: #E8637A; }
    50% { fill: #FF6B8A; }
}

/* Eye blink */
.eyelid-left, .eyelid-right {
    animation: blink 4s ease-in-out infinite;
}

.eyelid-left  { transform-origin: 93px 52px; }
.eyelid-right { transform-origin: 107px 52px; }

@keyframes blink {
    0%, 90%, 100% { height: 0; }
    95% { height: 12px; }
}

/* Pupil look around */
.pupil-left, .pupil-right {
    animation: lookAround 6s ease-in-out infinite;
}

@keyframes lookAround {
    0%, 40%, 100% { transform: translateX(0); }
    10% { transform: translateX(1.5px); }
    25% { transform: translateX(-1.5px); }
}

/* Panel lights blink */
.light-1 { animation: lightBlink 2s ease-in-out infinite; }
.light-2 { animation: lightBlink 2s ease-in-out 0.5s infinite; }
.light-3 { animation: lightBlink 2s ease-in-out 1s infinite; }

@keyframes lightBlink {
    0%, 80%, 100% { opacity: 1; }
    85%, 95% { opacity: 0.3; }
}

/* Signal waves */
.wave-1 { animation: wavePulse 2s ease-out infinite; }
.wave-2 { animation: wavePulse 2s ease-out 0.3s infinite; }
.wave-3 { animation: wavePulse 2s ease-out 0.6s infinite; }

@keyframes wavePulse {
    0% { opacity: 0; stroke-dashoffset: 20; }
    50% { opacity: 0.6; }
    100% { opacity: 0; stroke-dashoffset: 0; }
}

/* Dust particles */
.dust { animation: dustFloat 3s ease-in-out infinite; }
.dust-1 { animation-delay: 0s; }
.dust-2 { animation-delay: 0.8s; }
.dust-3 { animation-delay: 0.3s; }
.dust-4 { animation-delay: 1.2s; }

@keyframes dustFloat {
    0%, 100% { opacity: 0; transform: translate(0, 0); }
    30% { opacity: 0.5; }
    70% { opacity: 0.3; }
    100% { transform: translate(-8px, -12px); opacity: 0; }
}

/* Shadow pulse */
.rover-shadow {
    animation: shadowPulse 3s ease-in-out infinite;
}

@keyframes shadowPulse {
    0%, 100% { rx: 90; opacity: 0.6; }
    50% { rx: 85; opacity: 0.4; }
}

/* Smile wiggle */
.smile {
    animation: smileWiggle 6s ease-in-out infinite;
}

@keyframes smileWiggle {
    0%, 90%, 100% { d: path("M94 63 Q100 67 106 63"); }
    95% { d: path("M94 63 Q100 65 106 63"); }
}

/* Print: hide rover */
@media print {
    .rover-container {
        display: none !important;
    }
}
</style>
