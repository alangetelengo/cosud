{{-- Preloader : bulle centrée + logo ACSI (comme Progcaisse) --}}
<div id="preloader">
    <div class="preloader-bubble-wrapper">
        <div class="bubble-glow"></div>
        <div class="bubble-ring"></div>
        <img src="{{ asset('images/image-logo.jpg') }}" class="logo-in-bubble" alt="Armoiries du Congo">
    </div>
</div>

<style>
#preloader {
    position: fixed; inset: 0;
    background: linear-gradient(135deg, #0a1410, #0d1f18, #0f2820);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; overflow: hidden;
}
.preloader-bubble-wrapper {
    position: relative; width: 160px; height: 160px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    animation: bounceBubble 2s ease-in-out infinite;
}
.bubble-glow {
    position: absolute; width: 180px; height: 180px; border-radius: 50%;
    background: radial-gradient(circle, rgba(0,255,136,0.4), transparent 70%);
    filter: blur(35px); z-index: 0;
    animation: pulseBubbleGlow 2s ease-in-out infinite alternate;
}
.bubble-ring {
    position: absolute; width: 170px; height: 170px; border-radius: 50%;
    border: 2px solid rgba(0,255,136,0.6);
    box-shadow: 0 0 15px rgba(0,255,136,0.8), 0 0 30px rgba(0,180,100,0.4);
    z-index: 1; animation: rotateBubble 5s linear infinite;
}
.logo-in-bubble {
    position: relative; width: 100px; height: 100px; z-index: 2;
    border-radius: 50%; border: 2px solid rgba(0, 180, 100, 0.3);
    object-fit: cover; filter: drop-shadow(0 0 15px rgba(0,255,136,0.9));
}
@keyframes pulseBubbleGlow { 0% { transform: scale(1); opacity: 0.6; } 50% { transform: scale(1.2); opacity: 1; } 100% { transform: scale(1); opacity: 0.6; } }
@keyframes rotateBubble { to { transform: rotate(360deg); } }
@keyframes bounceBubble { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-40px); } }
#preloader.fade-out { opacity: 0; visibility: hidden; transition: opacity 0.8s ease, visibility 0.8s ease; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var preloader = document.getElementById("preloader");
    if (preloader) {
        window.addEventListener("load", function() {
            setTimeout(function() { preloader.classList.add("fade-out"); }, 800);
        });
        setTimeout(function() { preloader.classList.add("fade-out"); }, 3500);
    }
});
</script>
