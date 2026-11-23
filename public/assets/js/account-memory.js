const REMEMBERED_ACCOUNTS_KEY = 'goc-nho-remembered-accounts';

function getRememberedAccounts() {
  try {
    const accounts = JSON.parse(localStorage.getItem(REMEMBERED_ACCOUNTS_KEY) || '[]');
    return Array.isArray(accounts) ? accounts.filter(account => account && account.username) : [];
  } catch (_) {
    return [];
  }
}

function rememberAccount(user) {
  if (!user || !user.username) return;
  try {
    const accounts = getRememberedAccounts().filter(account => account.username !== user.username && Number(account.id) !== Number(user.id));
    accounts.unshift({ id: Number(user.id), username: user.username, fullName: user.full_name || user.username,
      avatar: user.avatar || 'assets/images/avatars/user-admin.svg', role: user.role || 'user' });
    localStorage.setItem(REMEMBERED_ACCOUNTS_KEY, JSON.stringify(accounts.slice(0, 10)));
  } catch (_) {}
}

function removeRememberedAccount(username) {
  try {
    const accounts = getRememberedAccounts().filter(account => account.username !== username);
    localStorage.setItem(REMEMBERED_ACCOUNTS_KEY, JSON.stringify(accounts));
  } catch (_) {}
}

function switchToRememberedAccount(username) {
  const account = getRememberedAccounts().find(item => item.username === username);
  if (!account) {
    window.location.href = 'accounts.html';
    return;
  }
  window.location.href = `accounts.html?account=${encodeURIComponent(account.username)}`;
}
