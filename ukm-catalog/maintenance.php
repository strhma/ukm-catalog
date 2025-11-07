<?php
// Maintenance mode page
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 3600'); // 1 hour
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - UKM Catalog</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .maintenance-container {
            text-align: center;
            max-width: 600px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .message {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #ffd700;
        }
        
        .message h3 {
            margin-top: 0;
            color: #ffd700;
        }
        
        .message p {
            margin-bottom: 0;
            line-height: 1.6;
        }
        
        .info {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .contact {
            margin-top: 30px;
        }
        
        .contact a {
            color: #ffd700;
            text-decoration: none;
            font-weight: bold;
        }
        
        .contact a:hover {
            text-decoration: underline;
        }
        
        .progress {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ffed4e);
            border-radius: 3px;
            animation: progress 3s ease-in-out infinite;
        }
        
        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: white;
            font-size: 1.5rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .social-links a:hover {
            opacity: 1;
        }
        
        .footer {
            margin-top: 30px;
            font-size: 0.8rem;
            opacity: 0.6;
        }
        
        @media (max-width: 768px) {
            .maintenance-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        
        <h1>Maintenance in Progress</h1>
        <p class="subtitle">UKM Catalog sedang dalam perbaikan</p>
        
        <div class="message">
            <h3>Maaf atas ketidaknyamanannya</h3>
            <p>
                Website kami sedang dalam tahap perbaikan dan pemeliharaan untuk meningkatkan pengalaman Anda. 
                Kami akan kembali online dalam waktu singkat dengan fitur-fitur yang lebih baik.
            </p>
        </div>
        
        <div class="progress">
            <div class="progress-bar"></div>
        </div>
        
        <div class="info">
            <strong>Estimasi Waktu:</strong> 1-2 jam<br>
            <strong>Terakhir Diperbarui:</strong> <?php echo date('d F Y H:i'); ?><br>
            <strong>Status:</strong> Perbaikan sistem dan optimasi database
        </div>
        
        <div class="contact">
            <p>
                Untuk informasi lebih lanjut, silakan hubungi:<br>
                📧 <a href="mailto:support@ukmcatalog.com">support@ukmcatalog.com</a><br>
                📱 <a href="tel:+6281234567890">+62 812-3456-7890</a>
            </p>
        </div>
        
        <div class="social-links">
            <a href="#" title="Facebook">📘</a>
            <a href="#" title="Twitter">🐦</a>
            <a href="#" title="Instagram">📷</a>
            <a href="#" title="WhatsApp">💬</a>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> UKM Catalog. All rights reserved.</p>
            <p>Terima kasih atas kesabaran Anda.</p>
        </div>
    </div>
    
    <script>
        // Auto refresh page every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
        
        // Show countdown if available
        function updateCountdown() {
            // In real implementation, this would fetch from server
            const now = new Date();
            const target = new Date(now.getTime() + (2 * 60 * 60 * 1000)); // 2 hours from now
            
            const diff = target - now;
            
            if (diff > 0) {
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                document.querySelector('.info').innerHTML += 
                    `<br><strong>Sisa Waktu:</strong> ${hours} jam ${minutes} menit ${seconds} detik`;
            }
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html>