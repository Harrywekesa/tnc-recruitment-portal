<style>
/* Clean Horizontal Sub-Navigation Matrix */
.admin-subnav-container {
    background: var(--green-900);
    color: #fff;
    margin-bottom: 32px;
}

/* Master Wrapper providing Relative Boundaries for the Absolute Scroll Arrows */
.admin-subnav-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.admin-subnav-list {
    display: flex;
    gap: 24px;
    width: 100%;
    
    /* Enable Native Touch Swiping */
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; 
    
    /* Brutally Hide the Ugly Visual Scrollbars Native to Browsers */
    -ms-overflow-style: none;
    scrollbar-width: none;
    scroll-behavior: smooth;
}

/* Hide Scrollbar specifically for Chrome, Safari and Opera */
.admin-subnav-list::-webkit-scrollbar {
    display: none;
}

.admin-subnav-list > a {
    padding: 12px 0;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: 0.2s;
    /* Ensure borders sit precisely at the bottom bounding box edge */
    margin-bottom: -1px; 
}

.admin-subnav-list > a.active {
    color: #fff;
    border-bottom: 3px solid var(--gold);
}

.admin-subnav-list > a:not(.active) {
    color: rgba(255,255,255,0.6);
}

.admin-subnav-list > a:hover {
    color: #fff;
}

/* -------------------------------------- */
/* DYNAMIC SCROLL INDICATOR ARROWS        */
/* -------------------------------------- */
.nav-scroll-btn {
    position: absolute;
    top: 0;
    bottom: 0;
    background: var(--green-900); /* Mask behind button perfectly matches header */
    border: none;
    color: var(--gold);
    font-size: 14px;
    cursor: pointer;
    z-index: 10;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none; /* Ignore clicks when inherently hidden */
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-scroll-btn.visible {
    opacity: 1;
    pointer-events: auto; /* Re-enable clicks when overflow dynamically occurs */
}

.nav-scroll-left {
    left: 0;
    background: linear-gradient(90deg, var(--green-900) 70%, transparent 100%);
    box-shadow: 12px 0 12px -5px var(--green-900);
}

.nav-scroll-right {
    right: 0;
    background: linear-gradient(-90deg, var(--green-900) 70%, transparent 100%);
    box-shadow: -12px 0 12px -5px var(--green-900);
}
</style>

<div class="admin-subnav-container">
  <div class="container" style="padding-top:24px;">
    
    <!-- System Status Banner -->
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
      <div>
        <h1 style="font-size:24px; font-weight:800; margin:0 0 4px 0;">HR Administration</h1>
        <div style="font-size:14px; opacity:0.8;">Trans Nzoia County Backend Portal</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:13px; opacity:0.8; margin-bottom:4px;">Logged in as: <strong style="color:var(--gold);"><?= h(auth_user()['username']??'Root') ?></strong></div>
        <span class="badge badge-success" style="background:#def1d7; color:var(--green-800);">● Live Secure Session</span>
      </div>
    </div>
    
    <!-- Dynamic Admin Sub-Nav Context Frame -->
    <div class="admin-subnav-wrapper">
      
      <!-- Interactive Left Anchor Pointer -->
      <button class="nav-scroll-btn nav-scroll-left" id="scrollNavLeft" onclick="scrollNav(-220)" title="Scroll Left">
          ◀
      </button>

      <div class="admin-subnav-list" id="adminSubNavList">
        <a href="index.php" class="<?= $admin_page==='index'?'active':'' ?>">📊 Dashboard Analytics</a>
        <a href="reports.php" class="<?= $admin_page==='reports'?'active':'' ?>">📑 Reporting Generators</a>
        <a href="jobs.php" class="<?= $admin_page==='jobs'?'active':'' ?>">💼 Jobs & Requisitions</a>
        <a href="applications.php" class="<?= $admin_page==='applications'?'active':'' ?>">📂 Applications & Triaging</a>
        <a href="interviews.php" class="<?= $admin_page==='interviews'?'active':'' ?>">📅 Interview Logistics</a>
        
        <?php if(has_role('superadmin')): ?>
          <a href="users.php" class="<?= $admin_page==='users'?'active':'' ?>">⚙ User Mgmt</a>
          <a href="logs.php" class="<?= $admin_page==='logs'?'active':'' ?>">📜 Activity Logs</a>
        <?php endif; ?>
      </div>

      <!-- Interactive Right Anchor Pointer -->
      <button class="nav-scroll-btn nav-scroll-right" id="scrollNavRight" onclick="scrollNav(220)" title="Scroll Right">
          ▶
      </button>

    </div>
    
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const navList = document.getElementById('adminSubNavList');
    const leftBtn = document.getElementById('scrollNavLeft');
    const rightBtn = document.getElementById('scrollNavRight');

    // Engine inherently calculating the bounding box overflow
    function checkScrollLimits() {
        if (!navList) return;
        
        // Calculate the maximum mathematical scrolling distance strictly
        const maxScroll = navList.scrollWidth - navList.clientWidth;
        
        // Display Left Anchor Arrow ONLY if scrolled physically inward from start
        if (navList.scrollLeft > 2) {
            leftBtn.classList.add('visible');
        } else {
            leftBtn.classList.remove('visible');
        }
        
        // Display Right Anchor Arrow ONLY if explicitly not at the exact far right box edge
        // Math.ceil accommodates sub-pixel calculations inherently forced by mobile OS zooming!
        if (Math.ceil(navList.scrollLeft) < maxScroll - 2 && maxScroll > 0) {
            rightBtn.classList.add('visible');
        } else {
            rightBtn.classList.remove('visible');
        }
    }

    // Instantly bind physical detection hooks dynamically
    navList.addEventListener('scroll', checkScrollLimits);
    window.addEventListener('resize', checkScrollLimits);
    
    // Initial UI execution after DOM geometry settles
    setTimeout(checkScrollLimits, 60);
});

// Programmatic physical offset shifting bound to the UI Pointers
function scrollNav(amount) {
    const navList = document.getElementById('adminSubNavList');
    if (navList) {
        navList.scrollBy({ left: amount, behavior: 'smooth' });
    }
}
</script>
