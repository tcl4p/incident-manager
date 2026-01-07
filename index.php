<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid') {
        $error = 'Access code not recognized. Please try again.';
    } elseif ($_GET['error'] === 'format') {
        $error = 'Access code must be 6 to 8 digits (8 digits recommended).';
    } elseif ($_GET['error'] === 'logout') {
        $error = 'You have been logged out.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>FD Incident Management - Department Access</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        body { background-color: #7b0000; color: #fff; }
        .keypad-card { max-width: 420px; margin: 2rem auto; background-color: #b30000; }
        .code-display { font-size: 2rem; letter-spacing: 0.4rem; text-align: center; }
        .keypad-button { font-size: 1.5rem; padding: 0.75rem 0; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">Fire Department Incident Management</span>
    </div>
</nav>

<div class="container">
    <div class="keypad-card card text-light mt-4 shadow">
        <div class="card-header text-center">Department Access Code</div>
        <div class="card-body">

            <?php if ($error !== ''): ?>
                <div class="alert alert-warning" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <!-- Access form (display + keypad + Enter) -->
            <form id="accessForm" method="post" action="auth_access.php" onsubmit="return validateAndSubmit();" autocomplete="off">
                <div class="mb-3">
                    <!-- This is now the REAL posted field -->
                    <input type="password"
                           id="access_code"
                           name="access_code"
                           class="form-control code-display text-center"
                           value=""
                           inputmode="numeric"
                           minlength="6"
                           maxlength="8"
                           required
                           autocomplete="off">
                    <div class="form-text text-light text-center">
                        Enter department access code (6–8 digits)
                    </div>
                </div>

                <!-- Keypad -->
                <div class="row g-2 text-center">
                    <?php
                    $keys = [
                        ['1','2','3'],
                        ['4','5','6'],
                        ['7','8','9'],
                    ];
                    foreach ($keys as $row):
                    ?>
                        <div class="col-12 d-flex gap-2 justify-content-center">
                            <?php foreach ($row as $digit): ?>
                                <button type="button"
                                        class="btn btn-light flex-fill keypad-button"
                                        onclick="appendDigit('<?php echo $digit; ?>')">
                                    <?php echo $digit; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="col-12 d-flex gap-2 justify-content-center mt-2">
                        <button type="button" class="btn btn-secondary flex-fill keypad-button" onclick="clearCode()">Clear</button>
                        <button type="button" class="btn btn-light flex-fill keypad-button" onclick="appendDigit('0')">0</button>
                        <button type="submit" class="btn btn-danger flex-fill keypad-button">Enter</button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-3">
                <a class="btn btn-outline-light w-100 py-3" href="add_department.php">Add New Department</a>
            </div>

        </div>
        <div class="card-footer text-center small text-light">
            Fire Department Incident Management System<br>
            With development assistance from ChatGPT (OpenAI)
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<script>
    function getCurrentCode() {
        const el = document.getElementById('access_code');
        return el ? el.value : '';
    }

    function setCurrentCode(code) {
        const el = document.getElementById('access_code');
        if (!el) return;
        el.value = code;
    }

    function appendDigit(digit) {
        let code = getCurrentCode();
        if (code.length >= 8) return;
        code += digit;
        setCurrentCode(code);
    }

    function clearCode() {
        setCurrentCode('');
    }

    function validateAndSubmit() {
        const code = getCurrentCode();
        if (!/^\d{6,8}$/.test(code)) {
            alert('Access code must be 6 to 8 digits (8 digits recommended).');
            return false;
        }
        return true;
    }

    // Numeric-only on paste/typing
    (function(){
        const el = document.getElementById('access_code');
        if (!el) return;
        el.addEventListener('input', function(){
            let v = el.value.replace(/\D+/g,'');
            if (v.length > 8) v = v.slice(0,8);
            if (v !== el.value) el.value = v;
        });
    })();
</script>
</body>
</html>
