<section class="auth-card" aria-labelledby="login-title">
    <p class="eyebrow">Aplikasi internal</p>
    <h1 id="login-title">Masuk ke Klinik Lab</h1>
    <p class="muted">Gunakan akun staf yang diberikan administrator.</p>

    <form method="post" action="/login" class="stack-form">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <label>
            Username
            <input name="username" type="text" autocomplete="username" required maxlength="64" autofocus>
        </label>
        <label>
            Password
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Masuk</button>
    </form>
</section>

