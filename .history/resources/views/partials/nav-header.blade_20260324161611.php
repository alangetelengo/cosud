{{-- Nav header : logo seul (hamburger déplacé dans la barre principale) --}}
<style>
.nav-header {
    width: 250px; height: 80px; position: fixed; top: 0; left: 0; z-index: 1100;
    background: linear-gradient(135deg, #0a1410 0%, #0d1f18 50%, #0f2820 100%);
    display: flex; align-items: center; justify-content: center; padding: 0 20px;
    border-right: 2px solid rgba(0, 180, 100, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), 0 0 40px rgba(0, 180, 100, 0.1);
    transition: width 0.3s ease;
}
.nav-header .brand-logo {
    display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: 700;
    color: #fff; text-decoration: none; letter-spacing: 1px; transition: all 0.3s ease;
}
.nav-header .brand-logo:hover { color: #00ff88; transform: translateX(3px); }
.nav-header .brand-logo img {
    height: 45px; width: 45px; flex-shrink: 0; border-radius: 50%;
    border: 2px solid rgba(0, 180, 100, 0.4); padding: 3px;
    background: rgba(15, 40, 32, 0.8); box-shadow: 0 0 15px rgba(0, 180, 100, 0.4);
    transition: all 0.3s ease; object-fit: cover;
}
.nav-header .brand-logo img:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 0 25px rgba(0, 180, 100, 0.6);
}
#main-wrapper.menu-toggle .nav-header { width: 80px; }
#main-wrapper.menu-toggle .nav-header .brand-logo span { display: none; }
#main-wrapper.menu-toggle .nav-header .brand-logo { justify-content: center; }
</style>

<div id="navHeader" class="nav-header">
    <a href="{{ url('/') }}" class="brand-logo">
        <img src="{{ asset('images/image-logo.jpg') }}" alt="Logo ACSI">
        <span class="sidebar-label">GED</span>
    </a>
</div>
