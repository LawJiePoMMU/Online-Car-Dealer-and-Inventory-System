<?php
session_start();

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: profile.php");
    exit;
}

// 1. 引入你原封不动的 database.php
require_once "../../Config/database.php";

// 2. 借用 database.php 里的变量，在这里直接生成高级的 PDO 连接
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

$name = $ic = $email = $phone = $address = $city = $state = $postcode = $password = $confirm_password = "";
$name_err = $email_err = $password_err = $confirm_password_err = $register_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $name = trim($_POST["name"]);
    $ic = trim($_POST["ic"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $city = trim($_POST["city"]);
    $state = trim($_POST["state"]);
    $postcode = trim($_POST["postcode"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if(empty($name)){ $name_err = "Required"; }
    if(empty($email)){ $email_err = "Required"; }
    
    // 后端密码严格验证 (防止前端被绕过)
    if(empty($password)){
        $password_err = "Password is required";     
    } else {
        $uppercase = preg_match('@[A-Z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if(!$uppercase || !$number || !$specialChars || strlen($password) < 8) {
            $password_err = "Must meet all password requirements.";
        }
    }

    if(empty($confirm_password)){
        $confirm_password_err = "Required";     
    } else {
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Passwords do not match";
        }
    }

    if(empty($email_err)){
        $sql = "SELECT user_id FROM users WHERE user_email = :email";
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if($stmt->rowCount() == 1){
                $email_err = "Email already taken";
            }
        }
    }
    
    if(empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        $sql = "INSERT INTO users (user_name, user_ic, user_email, user_phone, user_address, user_city, user_state, user_postcode, user_password, user_role, user_status, user_created_at) 
                VALUES (:name, :ic, :email, :phone, :address, :city, :state, :postcode, :password, 'Customer', 'Active', NOW())";
         
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':ic', $ic, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $stmt->bindParam(':city', $city, PDO::PARAM_STR);
            $stmt->bindParam(':state', $state, PDO::PARAM_STR);
            $stmt->bindParam(':postcode', $postcode, PDO::PARAM_STR);
            
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bindParam(':password', $param_password, PDO::PARAM_STR);
            
            if($stmt->execute()){
                header("location: login.php");
                exit;
            } else{
                $register_err = "Something went wrong. Please try again later.";
            }
        }
    }
}

include '../Includes/header.php';
?>

<div class="auth-page-override">
    <div class="auth-wrapper">
        <div class="auth-container register-container">
            
            <h1 class="auth-title">Create Account</h1>

            <?php 
            if(!empty($register_err)){
                echo '<div class="auth-error">' . $register_err . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <div class="register-grid">
                    
                    <div class="form-group">
                        <label class="auth-label">Full Name *</label>
                        <input type="text" name="name" class="auth-input" value="<?php echo $name; ?>" required>
                        <span class="auth-error-msg"><?php echo $name_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label class="auth-label">IC Number *</label>
                        <input type="text" name="ic" id="ic-input" class="auth-input" value="<?php echo $ic; ?>" placeholder="e.g. 050606-06-0548" maxlength="14">
                    </div>

                    <div class="form-group">
                        <label class="auth-label">Email Address *</label>
                        <input type="email" name="email" class="auth-input" value="<?php echo $email; ?>" required>
                        <span class="auth-error-msg"><?php echo $email_err; ?></span>
                    </div> 
                    <div class="form-group">
                        <label class="auth-label">Phone Number *</label>
                        <input type="text" name="phone" id="phone-input" class="auth-input" value="<?php echo $phone; ?>" placeholder="e.g. 011-35666968" maxlength="12">
                    </div>
                    
                    <div class="form-group">
                        <label class="auth-label">Password *</label>
                        <input type="password" name="password" id="main-pwd" class="auth-input pwd-input" required>
                        
                        <div class="pwd-toggle" onclick="togglePwd('main-pwd')">
                            <svg id="eye-main-pwd" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </div>
                        
                        <span class="auth-error-msg"><?php echo $password_err; ?></span>

                        <!-- 高级验证要求弹窗 -->
                        <div id="pwd-tracker" class="pwd-tracker">
                            <div id="req-len" class="req-item">
                                <div class="req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"></path></svg></div>
                                <span>At least 8 characters</span>
                            </div>
                            <div id="req-up" class="req-item">
                                <div class="req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"></path></svg></div>
                                <span>1 Uppercase letter (A-Z)</span>
                            </div>
                            <div id="req-num" class="req-item">
                                <div class="req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"></path></svg></div>
                                <span>1 Number (0-9)</span>
                            </div>
                            <div id="req-sym" class="req-item">
                                <div class="req-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"></path></svg></div>
                                <span>1 Symbol (e.g. !@#$%)</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="auth-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" id="confirm-pwd" class="auth-input pwd-input" required>
                        
                        <div class="pwd-toggle" onclick="togglePwd('confirm-pwd')">
                            <svg id="eye-confirm-pwd" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </div>
                        
                        <span class="auth-error-msg"><?php echo $confirm_password_err; ?></span>
                    </div>

                    <!-- 🔥 刚刚那条该死的分割线和占位空间已经被我彻底删除了！ -->

                    <div class="form-group full-width">
                        <label class="auth-label">Address *</label>
                        <input type="text" name="address" class="auth-input" value="<?php echo $address; ?>" placeholder="e.g. 123, Jalan Bukit Beruang">
                    </div>

                    <div class="three-col-row">
                        <div class="form-group">
                            <label class="auth-label">City *</label>
                            <input type="text" name="city" class="auth-input" value="<?php echo $city; ?>" placeholder="e.g. Ayer Keroh">
                        </div>
                        <div class="form-group">
                            <label class="auth-label">Postcode *</label>
                            <input type="text" name="postcode" class="auth-input" value="<?php echo $postcode; ?>" placeholder="e.g. 75450" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label class="auth-label">State *</label>
                            <select name="state" class="auth-input">
                                <option value="" disabled <?php if(empty($state)) echo "selected"; ?>>Select State</option>
                                <option value="Melaka" <?php if($state == "Melaka") echo "selected"; ?>>Melaka</option>
                                <option value="Johor" <?php if($state == "Johor") echo "selected"; ?>>Johor</option>
                                <option value="Kuala Lumpur" <?php if($state == "Kuala Lumpur") echo "selected"; ?>>Kuala Lumpur</option>
                                <option value="Selangor" <?php if($state == "Selangor") echo "selected"; ?>>Selangor</option>
                                <option value="Penang" <?php if($state == "Penang") echo "selected"; ?>>Penang</option>
                                <option value="Perak" <?php if($state == "Perak") echo "selected"; ?>>Perak</option>
                                <option value="Pahang" <?php if($state == "Pahang") echo "selected"; ?>>Pahang</option>
                                <option value="Kedah" <?php if($state == "Kedah") echo "selected"; ?>>Kedah</option>
                                <option value="Kelantan" <?php if($state == "Kelantan") echo "selected"; ?>>Kelantan</option>
                                <option value="Terengganu" <?php if($state == "Terengganu") echo "selected"; ?>>Terengganu</option>
                                <option value="Negeri Sembilan" <?php if($state == "Negeri Sembilan") echo "selected"; ?>>Negeri Sembilan</option>
                                <option value="Sabah" <?php if($state == "Sabah") echo "selected"; ?>>Sabah</option>
                                <option value="Sarawak" <?php if($state == "Sarawak") echo "selected"; ?>>Sarawak</option>
                            </select>
                        </div>
                    </div>

                </div>

                <button type="submit" class="auth-btn auth-btn-primary" style="margin-top: 30px;">Register</button>
            </form>

            <div class="auth-divider">
                <span>Already have an account?</span>
            </div>

            <a href="login.php" class="auth-btn auth-btn-secondary">Sign In Here</a>

        </div>
    </div>
</div>

<script>
    // 1. 小眼睛显示/隐藏密码
    function togglePwd(inputId) {
        const input = document.getElementById(inputId);
        const eyeSvg = document.getElementById('eye-' + inputId);
        
        if (input.type === 'password') {
            input.type = 'text';
            eyeSvg.style.color = '#0f172a'; 
        } else {
            input.type = 'password';
            eyeSvg.style.color = '#94a3b8'; 
        }
    }

    // 2. 高级实时密码验证交互 (修复了挡住下面地址栏的问题)
    const pwdInput = document.getElementById('main-pwd');
    const tracker = document.getElementById('pwd-tracker');
    
    // 点击密码框时显示弹窗
    pwdInput.addEventListener('focus', () => tracker.style.display = 'block');
    
    // 🔥 修复：只要离开密码框（点别的地方），直接隐藏弹窗，绝不挡路！
    pwdInput.addEventListener('blur', () => {
        tracker.style.display = 'none';
    });

    // 实时监测打字
    pwdInput.addEventListener('input', function() {
        const val = pwdInput.value;
        
        if(val.length >= 8) document.getElementById('req-len').className = 'req-item valid';
        else document.getElementById('req-len').className = 'req-item';
        
        if(/[A-Z]/.test(val)) document.getElementById('req-up').className = 'req-item valid';
        else document.getElementById('req-up').className = 'req-item';
        
        if(/[0-9]/.test(val)) document.getElementById('req-num').className = 'req-item valid';
        else document.getElementById('req-num').className = 'req-item';
        
        if(/[^A-Za-z0-9]/.test(val)) document.getElementById('req-sym').className = 'req-item valid';
        else document.getElementById('req-sym').className = 'req-item';
    });

    // 3. IC 和 电话自动格式化
    const icInput = document.getElementById('ic-input');
    if(icInput) {
        icInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); 
            let formattedValue = '';
            if (value.length > 0) formattedValue += value.substring(0, 6);
            if (value.length > 6) formattedValue += '-' + value.substring(6, 8);
            if (value.length > 8) formattedValue += '-' + value.substring(8, 12);
            e.target.value = formattedValue;
        });
    }

    const phoneInput = document.getElementById('phone-input');
    if(phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); 
            let formattedValue = '';
            if (value.length > 0) formattedValue += value.substring(0, 3);
            if (value.length > 3) formattedValue += '-' + value.substring(3, 11);
            e.target.value = formattedValue;
        });
    }
</script>