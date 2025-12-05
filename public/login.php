<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../user_panel/dashboard.php");
    exit;
}

$formData = $_SESSION['login_form'] ?? ['email' => ''];
$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['login_success'] ?? '';

unset($_SESSION['login_form'], $_SESSION['login_error'], $_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - LoFIMS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Arial', sans-serif; }
body {
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    background:#1e2a38;
    position:relative;
}

/* Aurora blobs with animated gradients */
.layer {
    position:absolute;
    border-radius:50%;
    width: 700px;
    height: 700px;
    top:-20%;
    left:-15%;
    background: radial-gradient(circle at center, #ff9a9e, #fad0c4);
    animation: floatLayer 30s linear infinite, gradientShift 15s ease-in-out infinite alternate;
    z-index:0;
}

.layer2 { width:500px; height:500px; top:40%; left:60%; animation-duration:35s, 18s; background: radial-gradient(circle at center, #a18cd1, #fbc2eb); }
.layer3 { width:300px; height:300px; top:60%; left:20%; animation-duration:28s, 20s; background: radial-gradient(circle at center, #fbc2eb, #a6c1ee); }

@keyframes floatLayer {
    0% { transform: translate(0,0) rotate(0deg); }
    25% { transform: translate(50px,-30px) rotate(90deg); }
    50% { transform: translate(-30px,40px) rotate(180deg); }
    75% { transform: translate(40px,20px) rotate(270deg); }
    100% { transform: translate(0,0) rotate(360deg); }
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Floating small circles */
.floating-circle {
    position:absolute;
    width:15px;
    height:15px;
    background: rgba(255,255,255,0.1);
    border-radius:50%;
    animation: floatCircle linear infinite;
}
.floating-circle:nth-child(1){top:10%; left:20%; animation-duration:18s;}
.floating-circle:nth-child(2){top:50%; left:70%; animation-duration:22s;}
.floating-circle:nth-child(3){top:70%; left:30%; animation-duration:20s;}

@keyframes floatCircle {
    0% { transform: translateY(0px) translateX(0px); opacity:0.5; }
    50% { transform: translateY(-20px) translateX(15px); opacity:0.8; }
    100% { transform: translateY(20px) translateX(-10px); opacity:0.5; }
}

/* Login container */
.login-container {
    background: rgba(255, 255, 255, 0.95);
    
    backdrop-filter: blur(10px);
    border-radius:20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    overflow:hidden;
    width:100%;
    max-width:500px;
    position:relative;
    z-index:1;
    animation: floatUpDown 6s ease-in-out infinite;
}

@keyframes floatUpDown {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

.login-header {
    background: linear-gradient(135deg, #1e2a38 0%, #16212b 100%);
    color:white;
    padding:30px;
    text-align:center;
    position:relative;
}

.login-header::before {
    content:'';
    position:absolute;
    bottom:0;
    left:0;
    width:100%;
    height:4px;
    background: linear-gradient(90deg, #1e90ff, #4facfe, #1e90ff);
    background-size:200% 100%;
    animation: gradientShift 3s ease infinite;
}

.login-header .logo {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:15px;
    margin-bottom:15px;
}

.login-header .logo i{ font-size:32px; color:#1e90ff; }
.login-header .logo h1 {
    font-size:28px;
    font-weight:900;
    background: linear-gradient(45deg, #1e90ff, #4facfe);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

.login-header p { color:#b0b0b0; font-size:14px; }

.login-form{padding:30px;}
.form-group{margin-bottom:20px;position:relative;}
.form-group label {
    display:block;
    margin-bottom:8px;
    font-weight:600;
    color:#1e2a38;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:8px;
}
.form-control{
    width:100%;
    padding:12px 15px;
    border:2px solid #e1e5e9;
    border-radius:10px;
    font-size:14px;
    transition: all 0.3s;
    background:#f8f9fa;
}
.form-control:focus{
    outline:none;
    border-color:#1e90ff;
    background:white;
    box-shadow:0 0 0 3px rgba(30,144,255,0.1);
}

.buttons{display:flex; gap:10px; margin-top:20px;}
.buttons .btn{
    flex:1;
    padding:12px 25px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.btn-primary {
    background: linear-gradient(45deg,#1e90ff,#4facfe);
    color:white;
    border:none;
    box-shadow:0 4px 15px rgba(30,144,255,0.3);
}
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(30,144,255,0.4);}
.btn-secondary{
    background:transparent;
    color:#1e2a38;
    border:2px solid #1e2a38;
}
.btn-secondary:hover{background:rgba(30,42,56,0.1); transform:translateY(-2px);}

.alert {padding:12px 15px; border-radius:8px; margin-bottom:20px; font-size:14px; display:flex; align-items:center; gap:10px;}
.alert-error{background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;}
.alert-success{background:#d4edda; color:#155724; border:1px solid #c3e6cb;}

@media(max-width:480px){
    .login-container{margin:10px;}
    .login-form{padding:20px;}
    .buttons{flex-direction:column;}
}

/* Particle canvas overlay */
#particle-canvas {
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    pointer-events:none;
    z-index:0;
}
</style>
</head>
<body>
    <!-- Aurora layers -->
    <div class="layer layer1"></div>
    <div class="layer layer2"></div>
    <div class="layer layer3"></div>

    <!-- Floating small circles -->
    <div class="floating-circle"></div>
    <div class="floating-circle"></div>
    <div class="floating-circle"></div>

    <!-- Particle canvas -->
    <canvas id="particle-canvas"></canvas>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-search"></i>
                <h1>LoFIMS</h1>
            </div>
            <p>Sign in to your account</p>
        </div>

        <div class="login-form">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../auth/login_process.php">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" class="form-control" id="email" name="email" required
                        value="<?= htmlspecialchars($formData['email']) ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="buttons">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../public/index.php'">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>

                <!-- REMOVED: Registration link section -->
            </form>
        </div>
    </div>

<script>
// Particle system
const canvas = document.getElementById('particle-canvas');
const ctx = canvas.getContext('2d');
let particles = [];
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});

const particleCount = 50;

// Initialize particles
for(let i=0;i<particleCount;i++){
    particles.push({
        x: Math.random()*canvas.width,
        y: Math.random()*canvas.height,
        size: Math.random()*4+1,
        speedX: (Math.random()-0.5)*0.5,
        speedY: (Math.random()-0.5)*0.5,
        color: `rgba(255,255,255,${Math.random()*0.3+0.1})`
    });
}

// Draw particles
function drawParticles(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    particles.forEach(p=>{
        ctx.beginPath();
        ctx.arc(p.x,p.y,p.size,0,Math.PI*2);
        ctx.fillStyle=p.color;
        ctx.fill();
        p.x+=p.speedX;
        p.y+=p.speedY;

        if(p.x<0||p.x>canvas.width) p.speedX*=-1;
        if(p.y<0||p.y>canvas.height) p.speedY*=-1;
    });
    requestAnimationFrame(drawParticles);
}

drawParticles();

// Particle trail effect
canvas.addEventListener('mousemove', e=>{
    particles.push({
        x:e.clientX,
        y:e.clientY,
        size:Math.random()*3+1,
        speedX:(Math.random()-0.5)*0.5,
        speedY:(Math.random()-0.5)*0.5,
        color:`rgba(255,255,255,0.3)`
    });
    if(particles.length>100) particles.splice(0, particles.length-100);
});
</script>
</body>
</html>