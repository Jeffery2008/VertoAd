<?php include __DIR__ . '/../header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">联系方式管理</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- 邮箱设置 -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">邮箱设置</h4>
                                </div>
                                <div class="card-body">
                                    <form id="emailForm">
                                        <div class="form-group">
                                            <label for="email">邮箱地址</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?php echo htmlspecialchars($contacts['email'] ?? ''); ?>"
                                                <?php echo isset($contacts['email_verified']) && $contacts['email_verified'] ? 'readonly' : ''; ?>>
                                            <?php if (isset($contacts['email_verified']) && $contacts['email_verified']): ?>
                                                <small class="text-success">已验证</small>
                                            <?php else: ?>
                                                <small class="text-warning">未验证</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!isset($contacts['email_verified']) || !$contacts['email_verified']): ?>
                                            <button type="submit" class="btn btn-primary">更新邮箱</button>
                                        <?php endif; ?>
                                    </form>
                                    
                                    <?php if (isset($contacts['email']) && !empty($contacts['email']) && (!isset($contacts['email_verified']) || !$contacts['email_verified'])): ?>
                                        <div class="mt-3">
                                            <form id="emailVerifyForm" class="verification-form" style="display: none;">
                                                <div class="form-group">
                                                    <label for="emailCode">验证码</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="emailCode" name="code" maxlength="6">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-secondary resend-code" data-type="email">重新发送</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-success">验证邮箱</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 手机号设置 -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">手机号设置</h4>
                                </div>
                                <div class="card-body">
                                    <form id="phoneForm">
                                        <div class="form-group">
                                            <label for="phone">手机号</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                value="<?php echo htmlspecialchars($contacts['phone'] ?? ''); ?>"
                                                <?php echo isset($contacts['phone_verified']) && $contacts['phone_verified'] ? 'readonly' : ''; ?>>
                                            <?php if (isset($contacts['phone_verified']) && $contacts['phone_verified']): ?>
                                                <small class="text-success">已验证</small>
                                            <?php else: ?>
                                                <small class="text-warning">未验证</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!isset($contacts['phone_verified']) || !$contacts['phone_verified']): ?>
                                            <button type="submit" class="btn btn-primary">更新手机号</button>
                                        <?php endif; ?>
                                    </form>
                                    
                                    <?php if (isset($contacts['phone']) && !empty($contacts['phone']) && (!isset($contacts['phone_verified']) || !$contacts['phone_verified'])): ?>
                                        <div class="mt-3">
                                            <form id="phoneVerifyForm" class="verification-form" style="display: none;">
                                                <div class="form-group">
                                                    <label for="phoneCode">验证码</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="phoneCode" name="code" maxlength="6">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-secondary resend-code" data-type="phone">重新发送</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-success">验证手机号</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 更新邮箱
    $('#emailForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '/user/contact/update-email',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#emailVerifyForm').show();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('系统错误');
            }
        });
    });
    
    // 更新手机号
    $('#phoneForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '/user/contact/update-phone',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#phoneVerifyForm').show();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('系统错误');
            }
        });
    });
    
    // 验证邮箱
    $('#emailVerifyForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '/user/contact/verify-email',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('系统错误');
            }
        });
    });
    
    // 验证手机号
    $('#phoneVerifyForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '/user/contact/verify-phone',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('系统错误');
            }
        });
    });
    
    // 重新发送验证码
    $('.resend-code').click(function() {
        const $btn = $(this);
        const type = $btn.data('type');
        
        $btn.prop('disabled', true);
        let countdown = 60;
        
        $.ajax({
            url: '/user/contact/resend-code',
            type: 'POST',
            data: { type: type },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    
                    // 倒计时
                    const timer = setInterval(() => {
                        $btn.text(`${countdown}秒后重试`);
                        countdown--;
                        
                        if (countdown < 0) {
                            clearInterval(timer);
                            $btn.prop('disabled', false).text('重新发送');
                        }
                    }, 1000);
                    
                } else {
                    toastr.error(response.message);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                toastr.error('系统错误');
                $btn.prop('disabled', false);
            }
        });
    });
});</script>

<?php include __DIR__ . '/../footer.php'; ?> 