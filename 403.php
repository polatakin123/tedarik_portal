<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Erişim Reddedildi | Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 3rem;
            max-width: 600px;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: #e74a3b;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.5rem;
            color: #5a5c69;
            margin-bottom: 2rem;
        }
        .error-image {
            max-width: 100%;
            height: auto;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg error-container">
                    <div class="error-code">403</div>
                    <div class="error-message">Erişim Reddedildi</div>
                    <p class="lead text-muted">Bu sayfayı görüntüleme izniniz bulunmamaktadır.</p>
                    
                    <div class="my-4">
                        <i class="bi bi-shield-lock" style="font-size: 5rem; color: #e74a3b;"></i>
                    </div>
                    
                    <div class="mt-4">
                        <a href="/projeler/savunma/" class="btn btn-primary btn-lg">
                            <i class="bi bi-house-door me-2"></i>Ana Sayfaya Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 