// Token Manager
const TokenManager = {
    setTokens: (accessToken, refreshToken) => {
        localStorage.setItem('access_token', accessToken);
        localStorage.setItem('refresh_token', refreshToken);
    },
    getAccessToken: () => localStorage.getItem('access_token'),
    getRefreshToken: () => localStorage.getItem('refresh_token'),
    clearTokens: () => {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
    },
    isTokenExpired: (token) => {
        if (!token) return true;
        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            return payload.exp * 1000 < Date.now();
        } catch {
            return true;
        }
    }
};

// Auth Fetch Class
class AuthFetch {
    constructor() {
        this.retryCount = 0;
        this.maxRetry = 1;
    }

    async request(url, options = {}) {
        const accessToken = TokenManager.getAccessToken();
        if (accessToken) {
            options.headers = {
                ...options.headers,
                'Authorization': `Bearer ${accessToken}`
            };
        }

        try {
            const response = await fetch(url, options);

            if (response.status === 401 && this.retryCount < this.maxRetry) {
                this.retryCount++;
                await this.refreshToken();
                return this.request(url, options);
            }

            return response;
        } catch (error) {
            console.error('Request failed:', error);
            throw error;
        }
    }

    async refreshToken() {
        const refreshToken = TokenManager.getRefreshToken();
        if (!refreshToken) {
            throw new Error('No refresh token available');
        }

        try {
            const response = await fetch('/api/auth/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({refresh_token: refreshToken})
            });

            if (!response.ok) {
                throw new Error('Token refresh failed');
            }

            const {access_token, refresh_token} = await response.json();
            TokenManager.setTokens(access_token, refresh_token || refreshToken);
        } catch (error) {
            TokenManager.clearTokens();
            showLoginScreen();
            throw error;
        }
    }

    async get(url, options) {
        return this.request(url, {...options, method: 'GET'});
    }

    async post(url, body, options) {
        return this.request(url, {
            ...options,
            method: 'POST',
            body: JSON.stringify(body),
            headers: {
                'Content-Type': 'application/json',
                ...(options?.headers || {})
            }
        });
    }

    async put(url, body, options) {
        return this.request(url, {
            ...options,
            method: 'PUT',
            body: JSON.stringify(body),
            headers: {
                'Content-Type': 'application/json',
                ...(options?.headers || {})
            }
        });
    }

    async delete(url, options) {
        return this.request(url, {...options, method: 'DELETE'});
    }
}

// Initialize auth fetch
const authFetch = new AuthFetch();

// DOM Elements
const loginScreen = document.getElementById('login-screen');
const dashboardScreen = document.getElementById('dashboard-screen');
const loginForm = document.getElementById('login-form');
const messageContainer = document.getElementById('message-container');
const messageText = document.getElementById('message-text');
const userEmailElement = document.getElementById('user-email');
const logoutBtn = document.getElementById('logout-btn');
const toggleModeBtn = document.getElementById('toggle-mode-btn');
const confirmPasswordContainer = document.getElementById('confirm-password-container');
const forgotPasswordBtn = document.getElementById('forgot-password-btn');
const forgotPasswordModal = document.getElementById('forgot-password-modal');
const closeForgotPasswordModal = document.getElementById('close-forgot-password-modal');
const cancelForgotPassword = document.getElementById('cancel-forgot-password');
const forgotPasswordForm = document.getElementById('forgot-password-form');
const forgotPasswordSubmit = document.getElementById('forgot-password-submit');
const themeToggle = document.getElementById('theme-toggle');

// State
let isLoginMode = true;
let currentBucket = null;

// Window resize handler for responsive sidebar
window.addEventListener('resize', function () {
    if (window.innerWidth < 768) {
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30');
        if (!sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    } else {
        sidebar.classList.remove('fixed', 'inset-y-0', 'left-0', 'z-30', '-translate-x-full');
    }
});

// Check if user is logged in
document.addEventListener('DOMContentLoaded', function () {
    // Load OAuth configuration
    fetch('/api/auth/config')
        .then(response => response.json())
        .then(config => {
            window.GOOGLE_CLIENT_ID = config.googleClientId;
            window.GITHUB_CLIENT_ID = config.githubClientId;
        })
        .catch(error => {
            console.error('Failed to load OAuth configuration:', error);
        });

    // Check if dark mode is enabled
    if (localStorage.getItem('darkMode') === 'true' ||
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches &&
            localStorage.getItem('darkMode') !== 'false')) {
        document.documentElement.classList.add('dark');
        themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }

    // Check if user is logged in
    const accessToken = TokenManager.getAccessToken();
    if (accessToken && !TokenManager.isTokenExpired(accessToken)) {
        showDashboard();
        fetchUserData();
        loadOverviewData();
    }
});

// Functions
function showMessage(message, isSuccess) {
    messageText.textContent = message;
    messageText.className = `p-3 rounded-md text-sm ${isSuccess ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-destructive/10 text-destructive'}`;
    messageContainer.classList.remove('hidden');
    setTimeout(() => {
        messageContainer.classList.add('hidden');
    }, 5000);
}

function toggleMode() {
    isLoginMode = !isLoginMode;
    const title = document.querySelector('#login-screen h2');
    const subtitle = document.querySelector('#login-screen p');

    title.textContent = isLoginMode ? 'Lite Supabase Dashboard' : 'Create Account';
    subtitle.textContent = isLoginMode ? 'Sign in to your account' : 'Sign up for a new account';
    toggleModeBtn.textContent = isLoginMode ? 'Sign up' : 'Sign in';
    loginForm.querySelector('button[type="submit"]').textContent = isLoginMode ? 'Sign in' : 'Sign up';

    confirmPasswordContainer.classList.toggle('hidden', isLoginMode);
    document.getElementById('confirm-password').required = !isLoginMode;
}

function showDashboard() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('dashboard-screen').classList.remove('hidden');

    // Load user info
    loadUserInfo();

    // Load overview data
    loadOverviewData();

    // Load notifications
    loadNotifications();

    // Load profile picture
    loadProfilePicture();
}

function showLoginScreen() {
    dashboardScreen.classList.add('hidden');
    loginScreen.classList.remove('hidden');
}

// Function to load user information
async function loadUserInfo() {
    try {
        const response = await authFetch.get('/api/auth/user');

        if (!response.ok) {
            throw new Error('Failed to fetch user data');
        }

        const userData = await response.json();
        userEmailElement.textContent = userData.email;

        // Update sidebar user info
        updateSidebarUserInfo(userData.email);

        // Update profile section
        document.getElementById('profile-email').value = userData.email;
        document.getElementById('profile-id').value = userData.id;
        document.getElementById('profile-last-login').value = new Date(userData.last_sign_in_at || Date.now()).toLocaleString();

        // Set user initials in avatar if no profile picture
        const initials = userData.email.split('@')[0].substring(0, 2).toUpperCase();
        document.querySelector('.avatar-container').innerHTML = initials;

        return userData;
    } catch (error) {
        console.error('Error loading user info:', error);
        showMessage(`Error loading user info: ${error.message}`, 'error');
        if (error.message.includes('401')) {
            handleLogout();
        }
        return null;
    }
}

async function fetchUserData() {
    return await loadUserInfo();
}

async function loadOverviewData() {
    try {
        // Show loading state
        document.getElementById('bucket-count').textContent = '-';
        document.getElementById('file-count').textContent = '-';
        document.getElementById('storage-used').textContent = '-';

        // Fetch storage stats
        const response = await authFetch.get(`${API_BASE_URL}/storage/stats`);

        if (response.ok) {
            const data = await response.json();
            document.getElementById('bucket-count').textContent = data.buckets || 0;
            document.getElementById('file-count').textContent = data.files || 0;
            document.getElementById('storage-used').textContent = formatBytes(data.size || 0);
        } else {
            const errorData = await response.json();
            showMessage(`Error loading overview data: ${errorData.message}`, 'error');
        }
    } catch (error) {
        showMessage(`Error loading overview data: ${error.message}`, 'error');
    }
}

// Function to load users data for Authentication section
async function loadUsers() {
    try {
        // Show loading state
        document.getElementById('total-users').textContent = '-';
        document.getElementById('confirmed-users').textContent = '-';
        document.getElementById('new-users-today').textContent = '-';
        document.getElementById('users-table-body').innerHTML = '<tr><td colspan="6" class="p-6 text-center text-muted-foreground">Loading users...</td></tr>';

        // Fetch users data
        const response = await authFetch.get('/api/auth/user');

        if (response.ok) {
            const data = await response.json();
            const users = Array.isArray(data) ? data : (data.users || []);

            // Calculate stats
            const total = users.length;
            const confirmed = users.filter(user => user.email_confirmed).length;

            // Calculate new users today
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const newToday = users.filter(user => {
                const createdDate = new Date(user.created_at);
                return createdDate >= today;
            }).length;

            // Update stats
            document.getElementById('total-users').textContent = total;
            document.getElementById('confirmed-users').textContent = confirmed;
            document.getElementById('new-users-today').textContent = newToday;
            document.getElementById('users-count').textContent = total;

            // Update users table
            if (users.length > 0) {
                const tableBody = document.getElementById('users-table-body');
                tableBody.innerHTML = '';

                users.forEach(user => {
                    const row = document.createElement('tr');
                    row.className = 'hover:bg-muted/50';

                    // Format dates
                    const createdAt = new Date(user.created_at).toLocaleString();
                    const lastSignIn = user.last_sign_in_at ? new Date(user.last_sign_in_at).toLocaleString() : 'Never';

                    // Determine status class
                    let statusClass = 'bg-amber-500/10 text-amber-500';
                    if (user.email_confirmed) {
                        statusClass = 'bg-green-500/10 text-green-500';
                    }

                    row.innerHTML = `
                                    <td class="p-3 text-sm">${user.email}</td>
                                    <td class="p-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                            ${user.email_confirmed ? 'Confirmed' : 'Pending'}
                                        </span>
                                    </td>
                                    <td class="p-3 text-sm">${user.provider || 'Email'}</td>
                                    <td class="p-3 text-sm text-muted-foreground">${createdAt}</td>
                                    <td class="p-3 text-sm text-muted-foreground">${lastSignIn}</td>
                                    <td class="p-3 text-sm text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button class="text-muted-foreground hover:text-foreground edit-user-btn" title="Edit User" data-user-id="${user.id}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="text-muted-foreground hover:text-destructive delete-user-btn" title="Delete User" data-user-id="${user.id}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                `;

                    tableBody.appendChild(row);
                });

                // Add event listeners to edit and delete buttons
                document.querySelectorAll('.edit-user-btn').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const userId = e.currentTarget.getAttribute('data-user-id');
                        editUser(userId);
                    });
                });

                document.querySelectorAll('.delete-user-btn').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const userId = e.currentTarget.getAttribute('data-user-id');
                        deleteUser(userId);
                    });
                });
            } else {
                document.getElementById('users-table-body').innerHTML = '<tr><td colspan="6" class="p-6 text-center text-muted-foreground">No users found</td></tr>';
            }
        } else {
            const errorData = await response.json();
            showMessage(`Error loading users: ${errorData.message || 'Unknown error'}`, 'error');
            document.getElementById('users-table-body').innerHTML = '<tr><td colspan="6" class="p-6 text-center text-destructive">Error loading users</td></tr>';
        }
    } catch (error) {
        showMessage(`Error loading users: ${error.message}`, 'error');
        document.getElementById('users-table-body').innerHTML = '<tr><td colspan="6" class="p-6 text-center text-destructive">Error loading users</td></tr>';
    }
}

// Function to edit user
function editUser(userId) {
    // This would open a modal to edit the user
    showMessage(`Edit user functionality not implemented yet for user ID: ${userId}`, 'info');
}

// Function to delete user
async function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        try {
            const response = await authFetch.delete(`/api/auth/user/${userId}`);

            if (response.ok) {
                showMessage('User deleted successfully', 'success');
                loadUsers(); // Reload users list
            } else {
                const errorData = await response.json();
                showMessage(`Error deleting user: ${errorData.message || 'Unknown error'}`, 'error');
            }
        } catch (error) {
            showMessage(`Error deleting user: ${error.message}`, 'error');
        }
    }
}

async function loadBuckets() {
    try {
        const response = await authFetch.get('/api/storage/buckets');

        if (!response.ok) {
            throw new Error('Failed to fetch buckets');
        }

        const buckets = await response.json();
        const bucketsListEl = document.getElementById('buckets-list');
        bucketsListEl.innerHTML = '';

        if (buckets.length === 0) {
            bucketsListEl.innerHTML = '<div class="p-6 text-center text-muted-foreground">No buckets found</div>';
            return;
        }

        buckets.forEach(bucket => {
            const bucketEl = document.createElement('div');
            bucketEl.className = 'flex items-center justify-between p-4 hover:bg-accent/50 cursor-pointer';

            bucketEl.innerHTML = `
                            <div class="flex items-center">
                                <div class="rounded-full bg-primary/10 p-2 mr-3">
                                    <i class="fas fa-folder text-primary"></i>
                                </div>
                                <div>
                                    <p class="font-medium">${bucket.name}</p>
                                    <span class="text-xs px-2 py-0.5 rounded-full ${bucket.public ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-muted text-muted-foreground'}">
                                        ${bucket.public ? 'Public' : 'Private'}
                                    </span>
                                </div>
                            </div>
                            <button class="delete-bucket text-sm text-destructive hover:text-destructive/80" data-bucket="${bucket.name}">
                                <i class="fas fa-trash"></i>
                            </button>
                        `;

            bucketEl.addEventListener('click', function (e) {
                if (!e.target.closest('.delete-bucket')) {
                    loadFiles(bucket.name);
                }
            });

            bucketsListEl.appendChild(bucketEl);
        });

        document.querySelectorAll('.delete-bucket').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const bucketName = this.getAttribute('data-bucket');
                if (confirm(`Are you sure you want to delete the bucket "${bucketName}"?`)) {
                    deleteBucket(bucketName);
                }
            });
        });
    } catch (error) {
        console.error('Error loading buckets:', error);
    }
}

async function loadFiles(bucketName) {
    try {
        currentBucket = bucketName;
        document.getElementById('current-bucket-name').textContent = bucketName;
        document.getElementById('files-container').classList.remove('hidden');

        const response = await authFetch.get(`/api/storage/buckets/${bucketName}/files`);

        if (!response.ok) {
            throw new Error('Failed to fetch files');
        }

        const files = await response.json();
        const filesListEl = document.getElementById('files-list');
        filesListEl.innerHTML = '';

        if (files.length === 0) {
            filesListEl.innerHTML = '<div class="p-6 text-center text-muted-foreground">No files found</div>';
            return;
        }

        files.forEach(file => {
            const fileEl = document.createElement('div');
            fileEl.className = 'flex items-center justify-between p-4 hover:bg-accent/50';

            const fileIcon = getFileIcon(file.name);
            const fileSize = formatBytes(file.metadata?.size || 0);

            fileEl.innerHTML = `
                            <div class="flex items-center flex-1 min-w-0">
                                <div class="rounded-full bg-primary/10 p-2 mr-3">
                                    <i class="${fileIcon} text-primary"></i>
                                </div>
                                <div class="truncate">
                                    <p class="font-medium truncate">${file.name}</p>
                                    <p class="text-xs text-muted-foreground">${fileSize}</p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <a href="/api/storage/buckets/${bucketName}/files/${file.name}" 
                                   class="text-sm text-primary hover:text-primary/80" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="delete-file text-sm text-destructive hover:text-destructive/80" data-file="${file.name}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;

            filesListEl.appendChild(fileEl);
        });

        document.querySelectorAll('.delete-file').forEach(btn => {
            btn.addEventListener('click', function () {
                const fileName = this.getAttribute('data-file');
                if (confirm(`Are you sure you want to delete the file "${fileName}"?`)) {
                    deleteFile(currentBucket, fileName);
                }
            });
        });
    } catch (error) {
        console.error('Error loading files:', error);
    }
}

async function createBucket() {
    const bucketName = document.getElementById('bucket-name').value;
    const isPublic = document.getElementById('bucket-public').value === 'true';
    const errorEl = document.getElementById('create-bucket-error');

    try {
        errorEl.classList.add('hidden');

        const response = await authFetch.post('/api/storage/buckets', {
            name: bucketName,
            public: isPublic
        });

        if (!response.ok) {
            const data = await response.json();
            throw new Error(data.message || 'Failed to create bucket');
        }

        closeCreateBucketModal();
        document.getElementById('bucket-name').value = '';
        loadBuckets();
        loadOverviewData();
    } catch (error) {
        errorEl.textContent = error.message;
        errorEl.classList.remove('hidden');
    }
}

async function deleteBucket(bucketName) {
    try {
        const response = await authFetch.delete(`/api/storage/buckets/${bucketName}`);

        if (!response.ok) {
            throw new Error('Failed to delete bucket');
        }

        loadBuckets();
        loadOverviewData();
    } catch (error) {
        console.error('Error deleting bucket:', error);
        alert('Failed to delete bucket: ' + error.message);
    }
}

async function uploadFile() {
    const fileInput = document.getElementById('file-input');
    const file = fileInput.files[0];
    const errorEl = document.getElementById('upload-file-error');
    const progressContainer = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressText = document.getElementById('upload-progress-text');

    if (!file) {
        errorEl.textContent = 'Please select a file';
        errorEl.classList.remove('hidden');
        return;
    }

    try {
        errorEl.classList.add('hidden');
        progressContainer.classList.remove('hidden');

        const formData = new FormData();
        formData.append('file', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/api/storage/buckets/${currentBucket}/files`);
        xhr.setRequestHeader('Authorization', `Bearer ${TokenManager.getAccessToken()}`);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.textContent = percent + '%';
            }
        });

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                closeUploadFileModal();
                fileInput.value = '';
                progressContainer.classList.add('hidden');
                progressBar.style.width = '0%';
                loadFiles(currentBucket);
                loadOverviewData();
            } else {
                let errorMessage = 'Upload failed';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                }

                errorEl.textContent = errorMessage;
                errorEl.classList.remove('hidden');
                progressContainer.classList.add('hidden');
            }
        };

        xhr.onerror = function () {
            errorEl.textContent = 'Network error occurred';
            errorEl.classList.remove('hidden');
            progressContainer.classList.add('hidden');
        };

        xhr.send(formData);
    } catch (error) {
        errorEl.textContent = error.message;
        errorEl.classList.remove('hidden');
        progressContainer.classList.add('hidden');
    }
}

async function deleteFile(bucketName, fileName) {
    try {
        const response = await authFetch.delete(`/api/storage/buckets/${bucketName}/files/${fileName}`);

        if (!response.ok) {
            throw new Error('Failed to delete file');
        }

        loadFiles(bucketName);
        loadOverviewData();
    } catch (error) {
        console.error('Error deleting file:', error);
        alert('Failed to delete file: ' + error.message);
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    try {
        if (!isLoginMode) {
            const confirmPassword = document.getElementById('confirm-password').value;
            if (password !== confirmPassword) {
                showMessage('Passwords do not match.', false);
                return;
            }
        }

        const endpoint = isLoginMode ? '/api/auth/login' : '/api/auth/signup';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({email, password}),
        });

        const result = await response.json();
        if (response.ok) {
            showMessage(isLoginMode ? 'Successfully logged in!' : 'Account created successfully!', true);
            TokenManager.setTokens(result.data.access_token, result.data.refresh_token);
            showDashboard();
            fetchUserData();
            loadOverviewData();
        } else {
            showMessage(result.data.message || 'An error occurred.', false);
        }
    } catch (error) {
        showMessage('An error occurred. Please try again.', false);
    }
}

async function handleLogout() {
    try {
        await authFetch.post('/api/auth/logout');
    } finally {
        TokenManager.clearTokens();
        showLoginScreen();
    }
}

function loginWithGoogle() {
    const state = Math.random().toString(36).substring(7);
    localStorage.setItem('oauthState', state);

    const googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    const params = new URLSearchParams({
        client_id: window.GOOGLE_CLIENT_ID,
        redirect_uri: window.location.origin + '/api/auth/google/callback',
        response_type: 'code',
        scope: 'email profile',
        state: state,
        prompt: 'select_account'
    });

    window.location.href = `${googleAuthUrl}?${params.toString()}`;
}

function loginWithGithub() {
    const state = Math.random().toString(36).substring(7);
    localStorage.setItem('oauthState', state);

    const githubAuthUrl = 'https://github.com/login/oauth/authorize';
    const params = new URLSearchParams({
        client_id: window.GITHUB_CLIENT_ID,
        redirect_uri: window.location.origin + '/api/auth/github/callback',
        scope: 'user:email',
        state: state
    });

    window.location.href = `${githubAuthUrl}?${params.toString()}`;
}

async function handleOAuthCallback() {
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');
    const provider = urlParams.get('provider') || (urlParams.get('scope') ? 'google' : 'github');
    const state = urlParams.get('state');

    // Verify state to prevent CSRF
    const savedState = localStorage.getItem(`oauth_state_${provider}`);
    if (state !== savedState) {
        showMessage('Invalid OAuth state, please try again', 'error');
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
        return;
        showMessage('Invalid authentication state.', false);
    }
}

async function handleForgotPassword(e) {
    e.preventDefault();
    const email = document.getElementById('reset-email').value;
    const errorEl = document.getElementById('forgot-password-error');

    try {
        errorEl.classList.add('hidden');

        const response = await fetch('/api/auth/forgot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({email}),
        });

        const data = await response.json();
        if (response.ok) {
            closeForgotPasswordModalFunc();
            showMessage('Password reset instructions have been sent to your email.', true);
        } else {
            errorEl.textContent = data.message || 'An error occurred while processing your request.';
            errorEl.classList.remove('hidden');
        }
    } catch (error) {
        errorEl.textContent = 'An error occurred. Please try again.';
        errorEl.classList.remove('hidden');
    }
}

// Helper Functions
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function getFileIcon(fileName) {
    const extension = fileName.split('.').pop().toLowerCase();

    const iconMap = {
        'pdf': 'fas fa-file-pdf',
        'doc': 'fas fa-file-word',
        'docx': 'fas fa-file-word',
        'xls': 'fas fa-file-excel',
        'xlsx': 'fas fa-file-excel',
        'ppt': 'fas fa-file-powerpoint',
        'pptx': 'fas fa-file-powerpoint',
        'jpg': 'fas fa-file-image',
        'jpeg': 'fas fa-file-image',
        'png': 'fas fa-file-image',
        'gif': 'fas fa-file-image',
        'svg': 'fas fa-file-image',
        'txt': 'fas fa-file-alt',
        'csv': 'fas fa-file-csv',
        'json': 'fas fa-file-code',
        'xml': 'fas fa-file-code',
        'html': 'fas fa-file-code',
        'css': 'fas fa-file-code',
        'js': 'fas fa-file-code',
        'zip': 'fas fa-file-archive',
        'rar': 'fas fa-file-archive',
        'mp3': 'fas fa-file-audio',
        'mp4': 'fas fa-file-video',
        'mov': 'fas fa-file-video',
        'avi': 'fas fa-file-video'
    };

    return iconMap[extension] || 'fas fa-file';
}

// Modal Functions
function showCreateBucketModal() {
    document.getElementById('create-bucket-modal').classList.remove('hidden');
}

function closeCreateBucketModal() {
    document.getElementById('create-bucket-modal').classList.add('hidden');
    document.getElementById('create-bucket-error').classList.add('hidden');
    document.getElementById('bucket-name').value = '';
}

function showUploadFileModal() {
    document.getElementById('upload-file-modal').classList.remove('hidden');
}

function closeUploadFileModal() {
    document.getElementById('upload-file-modal').classList.add('hidden');
    document.getElementById('upload-file-error').classList.add('hidden');
    document.getElementById('file-input').value = '';
    document.getElementById('upload-progress').classList.add('hidden');
}

function showForgotPasswordModal() {
    forgotPasswordModal.classList.remove('hidden');
}

function closeForgotPasswordModalFunc() {
    forgotPasswordModal.classList.add('hidden');
    document.getElementById('forgot-password-error').classList.add('hidden');
    document.getElementById('reset-email').value = '';
}

// Event Listeners
loginForm.addEventListener('submit', handleLogin);
logoutBtn.addEventListener('click', handleLogout);
toggleModeBtn.addEventListener('click', toggleMode);

// Google and GitHub login
document.getElementById('google-login-btn').addEventListener('click', loginWithGoogle);
document.getElementById('github-login-btn').addEventListener('click', loginWithGithub);

// Forgot password
forgotPasswordBtn.addEventListener('click', showForgotPasswordModal);
closeForgotPasswordModal.addEventListener('click', closeForgotPasswordModalFunc);
cancelForgotPassword.addEventListener('click', closeForgotPasswordModalFunc);
forgotPasswordForm.addEventListener('submit', handleForgotPassword);
forgotPasswordSubmit.addEventListener('click', function () {
    forgotPasswordForm.dispatchEvent(new Event('submit'));
});

// Theme toggle
themeToggle.addEventListener('click', function () {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    localStorage.setItem('darkMode', isDark);
    themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
});

// Create bucket
document.getElementById('create-bucket-btn').addEventListener('click', showCreateBucketModal);
document.getElementById('close-create-bucket-modal').addEventListener('click', closeCreateBucketModal);
document.getElementById('cancel-create-bucket').addEventListener('click', closeCreateBucketModal);
document.getElementById('create-bucket-submit').addEventListener('click', createBucket);

// Upload file
document.getElementById('upload-file-btn').addEventListener('click', showUploadFileModal);
document.getElementById('close-upload-file-modal').addEventListener('click', closeUploadFileModal);
document.getElementById('cancel-upload-file').addEventListener('click', closeUploadFileModal);
document.getElementById('upload-file-submit').addEventListener('click', uploadFile);

// Back to buckets
document.getElementById('back-to-buckets-btn').addEventListener('click', function () {
    document.getElementById('files-container').classList.add('hidden');
});

// Refresh overview
document.getElementById('refresh-overview').addEventListener('click', loadOverviewData);

// Navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function () {
        const section = this.getAttribute('data-section');

        // Update active link
        document.querySelectorAll('.nav-link').forEach(el => {
            el.classList.remove('bg-accent', 'text-accent-foreground');
        });
        this.classList.add('bg-accent', 'text-accent-foreground');

        // Show selected section
        document.querySelectorAll('.dashboard-section').forEach(el => {
            el.classList.add('hidden');
        });
        document.getElementById(`${section}-section`).classList.remove('hidden');

        // Load section data
        if (section === 'storage') {
            loadBuckets();
        } else if (section === 'overview') {
            loadOverviewData();
        } else if (section === 'auth') {
            loadUsers();
        }

        // Close mobile sidebar after navigation
        if (window.innerWidth < 768) {
            sidebar.classList.add('-translate-x-full');
        }
    });
});

// Check for OAuth callback on page load
if (window.location.search.includes('code=')) {
    handleOAuthCallback();
} else if (window.location.search.includes('error=')) {
    // Handle OAuth error
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const errorDescription = urlParams.get('error_description');
    showMessage(`Authentication error: ${errorDescription || error}`, 'error');
    // Clean up URL
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Sidebar Toggle Functionality
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarToggleMobile = document.getElementById('sidebar-toggle-mobile');
const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
const sidebarTexts = document.querySelectorAll('.sidebar-text');
const userProfileSidebar = document.getElementById('user-profile-sidebar');

function toggleSidebar() {
    const isCollapsed = sidebar.classList.contains('md:w-16');
    const sidebarToggleWrapper = document.getElementById('sidebar-toggle-wrapper');

    if (isCollapsed) {
        // Expand
        sidebar.classList.remove('md:w-16');
        sidebar.classList.add('md:w-64');
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-left text-base"></i>';
        sidebarTexts.forEach(text => text.classList.remove('hidden'));
        // 使用固定像素值而不是rem单位
        sidebarToggleWrapper.style.left = '256px';
        localStorage.setItem('sidebarCollapsed', 'false');
    } else {
        // Collapse
        sidebar.classList.remove('md:w-64');
        sidebar.classList.add('md:w-16');
        sidebarToggle.innerHTML = '<i class="fas fa-chevron-right text-base"></i>';
        sidebarTexts.forEach(text => text.classList.add('hidden'));
        // 使用固定像素值而不是rem单位
        sidebarToggleWrapper.style.left = '64px';
        localStorage.setItem('sidebarCollapsed', 'true');
    }
}

function toggleMobileSidebar() {
    sidebar.classList.toggle('-translate-x-full');
}

// Check if sidebar was collapsed previously
if (localStorage.getItem('sidebarCollapsed') === 'true') {
    sidebar.classList.remove('md:w-64');
    sidebar.classList.add('md:w-16');
    sidebarToggle.innerHTML = '<i class="fas fa-chevron-right text-base"></i>';
    sidebarTexts.forEach(text => text.classList.add('hidden'));
    document.getElementById('sidebar-toggle-wrapper').style.left = '64px';
}

// Add mobile sidebar class for small screens
if (window.innerWidth < 768) {
    sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', '-translate-x-full');
}

// Event listeners for sidebar toggles
sidebarToggle.addEventListener('click', toggleSidebar);
sidebarToggleMobile.addEventListener('click', toggleMobileSidebar);
mobileMenuToggle.addEventListener('click', toggleMobileSidebar);

// Add window resize listener to ensure button position is correct
window.addEventListener('resize', function () {
    const isCollapsed = sidebar.classList.contains('md:w-16');
    const sidebarToggleWrapper = document.getElementById('sidebar-toggle-wrapper');

    if (window.innerWidth >= 768) { // md breakpoint
        if (isCollapsed) {
            sidebarToggleWrapper.style.left = '64px';
        } else {
            sidebarToggleWrapper.style.left = '256px';
        }
    }
});

// Add event listener for refresh users button
document.getElementById('refresh-users').addEventListener('click', loadUsers);

// Add event listener for invite user button
document.getElementById('invite-user-btn').addEventListener('click', function () {
    // Show invite user modal or form
    inviteUser();
});

// Function to invite a new user
function inviteUser() {
    const email = prompt('Enter email address to invite:');
    if (!email) return;

    if (!validateEmail(email)) {
        showMessage('Please enter a valid email address', 'error');
        return;
    }

    // Send invitation
    sendInvitation(email);
}

// Function to validate email format
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Function to send invitation
async function sendInvitation(email) {
    try {
        const response = await authFetch.post(`/api/auth/invite`, {
            email: email
        });

        if (response.ok) {
            showMessage(`Invitation sent to ${email}`, 'success');
            loadUsers(); // Reload users to show the new invited user
        } else {
            const errorData = await response.json();
            showMessage(`Error sending invitation: ${errorData.message || 'Unknown error'}`, 'error');
        }
    } catch (error) {
        showMessage(`Error sending invitation: ${error.message}`, 'error');
    }
}

// Add event listener for user search
document.getElementById('user-search').addEventListener('input', function (e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#users-table-body tr');

    // Skip if we're showing a message (only one row with colspan)
    if (rows.length === 1 && rows[0].querySelector('td[colspan]')) {
        return;
    }

    let visibleCount = 0;

    rows.forEach(row => {
        const email = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
        if (email.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('users-count').textContent = visibleCount;
});

// Add event listeners for pagination
document.getElementById('prev-page').addEventListener('click', function () {
    showMessage('Pagination not implemented yet', 'info');
});

document.getElementById('next-page').addEventListener('click', function () {
    showMessage('Pagination not implemented yet', 'info');
});

// Notifications functionality
const notificationsToggle = document.getElementById('notifications-toggle');
const notificationsDropdown = document.getElementById('notifications-dropdown');
const notificationIndicator = document.getElementById('notification-indicator');
const notificationsList = document.getElementById('notifications-list');
const markAllReadBtn = document.getElementById('mark-all-read');

let notifications = [];

// Toggle notifications dropdown
notificationsToggle.addEventListener('click', () => {
    notificationsDropdown.classList.toggle('hidden');

    // Mark notifications as seen when dropdown is opened
    if (!notificationsDropdown.classList.contains('hidden')) {
        notificationIndicator.classList.add('hidden');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.add('hidden');
        }
    }, {once: true});
});

// Mark all notifications as read
markAllReadBtn.addEventListener('click', () => {
    notifications = notifications.map(notification => ({
        ...notification,
        read: true
    }));

    localStorage.setItem('notifications', JSON.stringify(notifications));
    renderNotifications();
    notificationIndicator.classList.add('hidden');
});

// Add notification
function addNotification(title, message, type = 'info') {
    const notification = {
        id: Date.now(),
        title,
        message,
        type,
        read: false,
        timestamp: new Date().toISOString()
    };

    notifications.unshift(notification);

    // Limit to 10 notifications
    if (notifications.length > 10) {
        notifications.pop();
    }

    localStorage.setItem('notifications', JSON.stringify(notifications));
    renderNotifications();
    notificationIndicator.classList.remove('hidden');
}

// Render notifications
function renderNotifications() {
    if (notifications.length === 0) {
        notificationsList.innerHTML = '<div class="p-4 text-center text-muted-foreground text-sm">No notifications</div>';
        return;
    }

    notificationsList.innerHTML = '';

    notifications.forEach(notification => {
        const date = new Date(notification.timestamp);
        const formattedDate = date.toLocaleString();

        const notificationEl = document.createElement('div');
        notificationEl.className = `p-3 border-b border-border ${notification.read ? 'bg-background' : 'bg-accent/20'}`;

        let iconClass = 'fa-info-circle text-primary';
        if (notification.type === 'success') {
            iconClass = 'fa-check-circle text-green-500';
        } else if (notification.type === 'error') {
            iconClass = 'fa-exclamation-circle text-destructive';
        } else if (notification.type === 'warning') {
            iconClass = 'fa-exclamation-triangle text-amber-500';
        }

        notificationEl.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium">${notification.title}</div>
                                <div class="text-sm text-muted-foreground">${notification.message}</div>
                                <div class="text-xs text-muted-foreground mt-1">${formattedDate}</div>
                            </div>
                            <button class="delete-notification ml-2 text-muted-foreground hover:text-destructive" data-id="${notification.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;

        notificationsList.appendChild(notificationEl);
    });

    // Add event listeners to delete buttons
    document.querySelectorAll('.delete-notification').forEach(button => {
        button.addEventListener('click', (e) => {
            const id = parseInt(e.currentTarget.dataset.id);
            notifications = notifications.filter(notification => notification.id !== id);
            localStorage.setItem('notifications', JSON.stringify(notifications));
            renderNotifications();

            // Update notification indicator
            const unreadNotifications = notifications.filter(notification => !notification.read);
            if (unreadNotifications.length === 0) {
                notificationIndicator.classList.add('hidden');
            }

            e.stopPropagation();
        });
    });

    // Check if there are any unread notifications
    const unreadNotifications = notifications.filter(notification => !notification.read);
    if (unreadNotifications.length > 0) {
        notificationIndicator.classList.remove('hidden');
    } else {
        notificationIndicator.classList.add('hidden');
    }
}

// Load notifications from localStorage
function loadNotifications() {
    const storedNotifications = localStorage.getItem('notifications');
    if (storedNotifications) {
        notifications = JSON.parse(storedNotifications);
        renderNotifications();
    }
}

// Update sidebar user info
function updateSidebarUserInfo(email) {
    document.getElementById('sidebar-user-email').textContent = email || 'user@example.com';

    // Update avatar with initials if no profile picture
    if (!document.getElementById('sidebar-avatar-img')) {
        const initials = getInitials(email || 'user@example.com');
        const avatarContainer = document.querySelector('.avatar-container');
        avatarContainer.innerHTML = initials;
    }
}

// Get initials from email
function getInitials(email) {
    if (!email) return '';

    // If it's an email address, get the part before @
    const name = email.split('@')[0];

    // If name contains a dot, treat it as first.last
    if (name.includes('.')) {
        const parts = name.split('.');
        return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }

    // Otherwise just use the first 2 characters
    return name.substring(0, 2).toUpperCase();
}

// Profile picture functionality
const uploadAvatarBtn = document.getElementById('upload-avatar-btn');
const removeAvatarBtn = document.getElementById('remove-avatar-btn');
const avatarUpload = document.getElementById('avatar-upload');
const changeAvatarBtn = document.getElementById('change-avatar-btn');
const profileAvatar = document.getElementById('profile-avatar');

// Open file dialog when upload button is clicked
uploadAvatarBtn.addEventListener('click', () => {
    avatarUpload.click();
});

// Open file dialog when change avatar button is clicked
changeAvatarBtn.addEventListener('click', () => {
    avatarUpload.click();
});

// Handle file selection
avatarUpload.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        showProfileError('Please select an image file');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'h-full w-full object-cover';
        img.id = 'profile-avatar-img';

        // Save to localStorage
        localStorage.setItem('profilePicture', e.target.result);

        // Update profile avatar
        profileAvatar.innerHTML = '';
        profileAvatar.appendChild(img);

        // Update sidebar avatar
        updateSidebarAvatar(e.target.result);

        // Show success notification
        addNotification('Profile Updated', 'Your profile picture has been updated successfully', 'success');
    };
    reader.readAsDataURL(file);
});

// Remove profile picture
removeAvatarBtn.addEventListener('click', () => {
    // Remove from localStorage
    localStorage.removeItem('profilePicture');

    // Update profile avatar with initials
    const email = document.getElementById('profile-email').value;
    const initials = getInitials(email);
    profileAvatar.innerHTML = initials;

    // Update sidebar avatar
    updateSidebarAvatar();

    // Show notification
    addNotification('Profile Updated', 'Your profile picture has been removed', 'info');
});

// Update sidebar avatar
function updateSidebarAvatar(profilePicture = null) {
    const avatarContainer = document.querySelector('.avatar-container');

    if (profilePicture) {
        avatarContainer.innerHTML = `<img src="${profilePicture}" class="h-full w-full object-cover" id="sidebar-avatar-img">`;
    } else {
        const email = document.getElementById('sidebar-user-email').textContent;
        const initials = getInitials(email);
        avatarContainer.innerHTML = initials;
    }
}

// Show profile error
function showProfileError(message) {
    const errorEl = document.getElementById('profile-error');
    errorEl.textContent = message;
    errorEl.classList.remove('hidden');

    setTimeout(() => {
        errorEl.classList.add('hidden');
    }, 5000);
}

// Load profile picture on init
function loadProfilePicture() {
    const profilePicture = localStorage.getItem('profilePicture');
    if (profilePicture) {
        // Update profile avatar
        const img = document.createElement('img');
        img.src = profilePicture;
        img.className = 'h-full w-full object-cover';
        img.id = 'profile-avatar-img';

        if (profileAvatar) {
            profileAvatar.innerHTML = '';
            profileAvatar.appendChild(img);
        }

        // Update sidebar avatar
        updateSidebarAvatar(profilePicture);
    }
}