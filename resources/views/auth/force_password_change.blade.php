<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور - نظام المحاسبة المتقدم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 460px;
            width: 100%;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e0e0e0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-save {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 12px;
            font-weight: bold;
        }

        .logo {
            font-size: 46px;
            color: #667eea;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="password-card">
        <div class="text-center mb-4">
            <i class="fas fa-key logo"></i>
            <h2>تغيير كلمة المرور</h2>
            <p class="text-muted mb-0">يجب تعيين كلمة مرور جديدة قبل متابعة استخدام النظام</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ $errors->first() }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            <div class="mb-3">
                <label for="password" class="form-label"><i class="fas fa-lock ms-2"></i> كلمة المرور الجديدة</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label"><i class="fas fa-lock ms-2"></i> تأكيد كلمة المرور</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary btn-save w-100 mb-3">
                <i class="fas fa-save ms-2"></i>
                حفظ كلمة المرور والمتابعة
            </button>
        </form>

        <div class="text-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link text-danger text-decoration-none">
                    <i class="fas fa-sign-out-alt ms-2"></i>تسجيل خروج
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
