<?php
// Session-based login for officials. Passwords are stored hashed.
class Auth
{
    public function __construct(private UserRepository $users) {}

    public function attempt(string $username, string $password): bool
    {
        $u = $this->users->findByUsername($username);
        if ($u && password_verify($password, $u['password_hash'])) {
            $_SESSION['uid'] = $u['id'];
            return true;
        }
        return false;
    }

    public function check(): bool { return !empty($_SESSION['uid']); }

    public function logout(): void { $_SESSION = []; session_destroy(); }
}
