<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LoFIMS - Homepage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial',sans-serif; }
body { background:#f0f4ff; color:#333; }

/* LOGOUT SUCCESS MESSAGE */
.logout-success-message {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    animation: slideInRight 0.3s ease-out;
}
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}

/* HEADER */
header {
    width:100%;
    padding:20px 60px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}
.logo-placeholder {
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:bold;
    color:#0a3d62;
    font-size:18px;
}
.logo-placeholder img {
    height:50px;
    border-radius:10px;
    transition: transform 0.3s;
}
.logo-placeholder:hover img { transform: rotate(5deg); }
nav ul {
    list-style:none;
    display:flex;
    gap:25px;
    align-items:center;
}
nav ul li a {
    text-decoration:none;
    font-size:16px;
    color:#0a3d62;
    font-weight:500;
    position: relative;
    padding: 5px 0;
    transition: color 0.3s;
}
nav ul li a:hover { color:#1e90ff; }
nav ul li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: #1e90ff;
    transition: width 0.3s;
}
nav ul li a:hover::after { width: 100%; }
.login-btn {
    padding:10px 25px;
    background:linear-gradient(45deg,#1e90ff,#4facfe,#1e90ff);
    background-size:300% 300%;
    color:white;
    font-size:14px;
    font-weight:bold;
    border-radius:12px;
    border:2px solid rgba(30,144,255,0.8);
    cursor:pointer;
    backdrop-filter:blur(8px);
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    animation:gradientShift 3s ease infinite;
    transition:transform 0.3s, box-shadow 0.3s;
}
.login-btn:hover {
    transform:translateY(-4px);
    box-shadow:0 12px 25px rgba(0,0,0,0.25);
}
@keyframes gradientShift {
    0% { background-position:0% 50%; }
    50% { background-position:100% 50%; }
    100% { background-position:0% 50%; }
}

/* FADE-IN ANIMATION */
.fade-in { opacity: 0; transform: translateY(20px); transition: opacity 1s ease-out, transform 1s ease-out; }
.fade-in.visible { opacity: 1; transform: translateY(0); }

/* MAIN CONTENT */
.content-wrapper {
    animation:bgGradient 15s ease infinite;
    background:linear-gradient(-45deg,#e0f0ff,#f9faff,#d0e8ff,#cce0ff);
    background-size:400% 400%;
    transition:all 0.5s;
    padding:80px 20px;
    position: relative;
    overflow: hidden;
}
@keyframes bgGradient { 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }
.content {
    display:flex;
    justify-content:center;
    align-items:flex-start;
    gap:60px;
    flex-wrap:wrap;
    min-height:500px;
}
.content-text {
    flex:1 1 500px;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    padding:80px 50px;
    border-radius:25px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    animation:floatUpDown 6s ease-in-out infinite;
    position: relative;
    overflow: hidden;
}
.content-text::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}
.big-title { font-size:80px; font-weight:900; color:#0a3d62; line-height:1.1; margin-bottom:30px; background: linear-gradient(45deg, #0a3d62, #1e90ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 2px 2px 4px rgba(0,0,0,0.1); }
.slogan { font-size:22px; color:#555; margin-bottom:40px; }
.cta-buttons { display: flex; gap: 15px; margin-top: 30px; }
.cta-btn {
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cta-primary { background: #1e90ff; color: white; box-shadow: 0 5px 15px rgba(30,144,255,0.3); }
.cta-primary:hover { background: #0d7bd4; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(30,144,255,0.4); }
.cta-secondary { background: transparent; color: #0a3d62; border: 2px solid #0a3d62; }
.cta-secondary:hover { background: rgba(10,61,98,0.1); transform: translateY(-3px); }

.divider { width:5px; height:400px; background:#0a3d62; border-radius:4px; animation:floatUpDown 6s ease-in-out infinite alternate; position: relative; overflow: hidden; }
.divider::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, transparent, #1e90ff, transparent);
    animation: shine 2s ease-in-out infinite;
}
@keyframes shine { 0% { transform: translateY(-100%); } 100% { transform: translateY(100%); } }

.right-panel {
    flex:0 0 320px;
    text-align:center;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:25px;
    animation:floatUpDown 6s ease-in-out infinite alternate-reverse;
}
.right-title { font-size:40px; font-weight:800; color:#0a3d62; margin-bottom:25px; text-shadow: 1px 1px 3px rgba(0,0,0,0.1); }
.box-container { display:flex; flex-direction:column; gap:30px; align-items:center; }
.info-box {
    width:320px;
    padding:35px;
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    border-radius:25px;
    font-size:24px;
    font-weight:bold;
    color:white;
    cursor:pointer;
    border:2px solid rgba(41,128,185,0.8);
    display:flex;
    align-items:center;
    justify-content:center;
    gap:15px;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}
.info-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}
.info-box:hover::before { left: 100%; }
.info-box:hover { transform:translateY(-6px) translateX(5px); background:rgba(41,128,185,0.9); box-shadow:0 15px 25px rgba(0,0,0,0.2); }

@keyframes floatUpDown { 0%{transform:translateY(0px);} 50%{transform:translateY(-15px);} 100%{transform:translateY(0px);} }

/* HOW IT WORKS */
.how-it-works-wrapper {
    padding:80px 20px;
    text-align:center;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:60px auto;
    border-radius:25px;
    max-width:1200px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    position: relative;
    overflow: hidden;
}
.how-it-works-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}
.section-title {
    font-size:48px;
    font-weight:800;
    color:#0a3d62;
    margin-bottom:50px;
    position: relative;
    display: inline-block;
}
.section-title::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: #1e90ff;
    border-radius: 2px;
}
.steps-container {
    display:flex;
    justify-content:center;
    gap:40px;
    flex-wrap:wrap;
}
.step-card {
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    padding:40px 30px;
    border-radius:25px;
    width:280px;
    color:white;
    font-weight:bold;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:20px;
    animation:floatStep 6s ease-in-out infinite alternate;
    position: relative;
    overflow: hidden;
}
.step-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: #1e90ff;
}
.step-card i { color:#fff; font-size: 48px; transition: transform 0.3s; }
.step-card:hover i { transform: scale(1.1); }
.step-card h3 { font-size:24px; margin-bottom:10px; }
.step-card p { font-size:16px; color:#f0f8ff; text-align: center; line-height: 1.5; }
.step-card:hover { transform:translateY(-12px); background:rgba(41,128,185,0.85); box-shadow: 0 20px 30px rgba(0,0,0,0.2); }

/* ANNOUNCEMENTS */
.announcements-wrapper {
    padding:60px 20px;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:60px auto;
    border-radius:25px;
    max-width:1200px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    text-align:center;
    position: relative;
    overflow: hidden;
}
.announcements-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}
.announcement {
    background:rgba(52,152,219,0.8);
    padding:25px 30px;
    margin:20px 0;
    border-radius:15px;
    color:white;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    text-align: left;
    position: relative;
    overflow: hidden;
}
.announcement::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: #1e90ff;
}
.announcement:hover {
    transform:translateY(-6px);
    background:rgba(41,128,185,0.85);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}
.announcement-title { font-size:20px; font-weight:bold; margin-bottom:8px; }
.announcement-date { font-size:14px; color:#f0f8ff; margin-bottom:10px; display: flex; align-items: center; gap: 5px; }
.announcement-text { font-size:16px; color:#f0f8ff; line-height: 1.5; }
.view-all { display: inline-block; margin-top: 20px; color: #1e90ff; font-weight: bold; text-decoration: none; transition: all 0.3s; padding: 8px 15px; border-radius: 8px; border: 2px solid transparent; }
.view-all:hover { background: rgba(30,144,255,0.1); transform: translateX(5px); border-color: #1e90ff; }

/* STATISTICS */
.stats-wrapper {
    padding:80px 20px;
    text-align:center;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(8px);
    margin:60px auto;
    border-radius:25px;
    max-width:1200px;
    box-shadow:0 12px 30px rgba(0,0,0,0.12);
    position: relative;
    overflow: hidden;
}
.stats-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size: 200% 100%;
    animation: gradientShift 3s ease infinite;
}
.stats-container { display:flex; justify-content:center; gap:40px; flex-wrap:wrap; margin-top:50px; }
.stat-card {
    background:rgba(52,152,219,0.8);
    backdrop-filter:blur(8px);
    padding:40px 30px;
    border-radius:25px;
    width:280px;
    color:white;
    font-weight:bold;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:20px;
    font-size:18px;
    box-shadow:0 12px 25px rgba(0,0,0,0.15);
    animation:floatStep 6s ease-in-out infinite alternate;
    transition:transform 0.3s, background 0.3s, box-shadow 0.3s;
    position: relative;
    overflow: hidden;
}
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: #1e90ff; }
.stat-card:hover { transform:translateY(-10px); background:rgba(41,128,185,0.85); box-shadow: 0 18px 30px rgba(0,0,0,0.2); }
.stat-number { font-size:42px; font-weight:900; color:#fff; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
.stat-label { font-size:20px; color:#f0f8ff; }

/* FOOTER */
footer {
    width:100%;
    padding:40px 20px 20px 20px;
    background:linear-gradient(120deg,#cce0ff,#a0c4ff);
    backdrop-filter:blur(8px);
    border-top:2px solid #b0c4de;
    border-radius:20px 20px 0 0;
    text-align:center;
    margin-top:80px;
    color:#0a3d62;
}
footer a { color:#0a3d62; text-decoration:none; font-weight:bold; transition:all 0.3s; }
footer a:hover { color:#1e90ff; }

</style>
</head>
<body>

<!-- LOGOUT SUCCESS MESSAGE (ADDED HERE) -->
<?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
<div id="logoutMessage" class="logout-success-message">
    <i class="fas fa-check-circle"></i> 
    <?php 
    if (isset($_GET['message'])) {
        echo htmlspecialchars(urldecode($_GET['message']));
    } else {
        echo 'You have been successfully logged out!';
    }
    ?>
</div>

<script>
    // Auto-hide logout message after 3 seconds
    setTimeout(function() {
        const message = document.getElementById('logoutMessage');
        if (message) {
            message.style.animation = 'slideOutRight 0.3s ease-out forwards';
            setTimeout(function() {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 300);
        }
    }, 3000);
</script>
<?php endif; ?>

<!-- HEADER -->
<header>
    <div class="logo-placeholder">
        <img src="assets/images/Background_Images.jpg" alt="TUP Lopez Logo">
        <span>LoFIMS</span>
    </div>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="lost_items.php">Lost Items</a></li>
            <li><a href="found_items.php">Found Items</a></li>
            <li><a href="claim_item.php">Claims</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="contact.php">Contact</a></li>
            <li><button class="login-btn">Login</button></li>
        </ul>
    </nav>
</header>

<!-- MAIN CONTENT -->
<div class="content-wrapper fade-in">
    <div class="content">
        <div class="content-text fade-in">
            <div class="big-title">Find. Report. Return.</div>
            <div class="slogan">The official Lost and Found Management System for TUP Lopez Quezon that helps manage lost and found items efficiently.</div>
            <div class="cta-buttons">
                <button class="cta-btn cta-primary"><i class="fas fa-search"></i> Search Lost Items</button>
                <button class="cta-btn cta-secondary"><i class="fas fa-plus-circle"></i> Report Item</button>
            </div>
        </div>
        <div class="divider"></div>
        <div class="right-panel fade-in">
            <div class="right-title">TUP-LoFIMS</div>
            <div class="box-container">
                <div class="info-box fade-in"><i class="fas fa-hand-holding"></i> Claims</div>
                <div class="info-box fade-in"><i class="fas fa-search"></i> Lost Items</div>
                <div class="info-box fade-in"><i class="fas fa-box"></i> Found Items</div>
            </div>
        </div>
    </div>
</div>

<!-- HOW IT WORKS -->
<div class="how-it-works-wrapper fade-in">
    <div class="section-title">How It Works</div>
    <div class="steps-container">
        <div class="step-card fade-in"><i class="fas fa-search"></i><h3>Step 1</h3><p>Search for a lost item in the system using keywords or categories.</p></div>
        <div class="step-card fade-in"><i class="fas fa-plus-circle"></i><h3>Step 2</h3><p>Report found items to make them available for claim.</p></div>
        <div class="step-card fade-in"><i class="fas fa-hand-holding"></i><h3>Step 3</h3><p>Claim your lost items securely through the system.</p></div>
    </div>
</div>

<!-- ANNOUNCEMENTS -->
<div class="announcements-wrapper fade-in">
    <div class="section-title">Announcements</div>
    <div class="announcement fade-in">
        <div class="announcement-title">System Maintenance</div>
        <div class="announcement-date"><i class="fas fa-calendar-alt"></i> 2025-12-05</div>
        <div class="announcement-text">The system will be under maintenance from 10PM to 12AM.</div>
    </div>
    <div class="announcement fade-in">
        <div class="announcement-title">New Feature</div>
        <div class="announcement-date"><i class="fas fa-calendar-alt"></i> 2025-12-01</div>
        <div class="announcement-text">You can now track the status of your reported items in real-time.</div>
    </div>
    <a href="#" class="view-all">View All Announcements</a>
</div>

<!-- STATISTICS -->
<div class="stats-wrapper fade-in">
    <div class="section-title">Statistics</div>
    <div class="stats-container">
        <div class="stat-card fade-in"><div class="stat-number" data-target="150">0</div><div class="stat-label">Lost Items</div></div>
        <div class="stat-card fade-in"><div class="stat-number" data-target="120">0</div><div class="stat-label">Found Items</div></div>
        <div class="stat-card fade-in"><div class="stat-number" data-target="80">0</div><div class="stat-label">Claims</div></div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    &copy; 2025 LoFIMS - TUP Lopez. All Rights Reserved.
</footer>

<script>
// Animated counter
const counters = document.querySelectorAll('.stat-number');
const duration = 2000;
const animate = counter => {
    const target = +counter.getAttribute('data-target');
    const increment = target / (duration / 16);
    let current = 0;
    const update = () => {
        current += increment;
        if (current < target) {
            counter.textContent = Math.ceil(current);
            requestAnimationFrame(update);
        } else { counter.textContent = target; }
    };
    update();
};
const observerCounter = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animate(entry.target);
            observerCounter.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
counters.forEach(counter => observerCounter.observe(counter));

// BUTTON LINKS
document.querySelector('.login-btn').onclick = () => { window.location.href = "login.php"; };
document.querySelector('.cta-primary').onclick = () => { window.location.href = "lost_items.php"; };
document.querySelector('.cta-secondary').onclick = () => { window.location.href = "found_items.php"; };
document.querySelectorAll('.info-box').forEach(box => {
    box.onclick = () => {
        const action = box.textContent.trim();
        if (action === "Claims") window.location.href = "claim_item.php";
        if (action === "Lost Items") window.location.href = "lost_items.php";
        if (action === "Found Items") window.location.href = "found_items.php";
    };
});

// FADE-IN ON SCROLL
const faders = document.querySelectorAll('.fade-in');
const appearOptions = { threshold: 0.2, rootMargin: "0px 0px -50px 0px" };
const appearOnScroll = new IntersectionObserver(function(entries, appearOnScroll) {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('visible');
        appearOnScroll.unobserve(entry.target);
    });
}, appearOptions);
faders.forEach(fader => appearOnScroll.observe(fader));

</script>
</body>
</html>