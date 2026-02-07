let currentLanguage = 'en';

// Initialize the app
document.addEventListener('DOMContentLoaded', function() {
    updateLanguage();
    setupEventListeners();
});

function setupEventListeners() {
    // Form submissions
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('resetForm').addEventListener('submit', handlePasswordReset);
}

function changeLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('language', lang);
    updateLanguage();
}

function updateLanguage() {
    const lang = localStorage.getItem('language') || 'en';
    currentLanguage = lang;
    document.getElementById('languageSelect').value = lang;

    // Update all text elements
    document.getElementById('appName').textContent = getTranslation(lang, 'appName');
    document.getElementById('tagline').textContent = getTranslation(lang, 'tagline');
    document.getElementById('formTitle').textContent = getTranslation(lang, 'formTitle');
    document.getElementById('phoneLabel').textContent = getTranslation(lang, 'phoneLabel');
    document.getElementById('passwordLabel').textContent = getTranslation(lang, 'passwordLabel');
    document.getElementById('submitBtn').textContent = getTranslation(lang, 'submitBtn');
    document.getElementById('forgotPasswordBtn').textContent = getTranslation(lang, 'forgotPassword');
    document.getElementById('resetDescription').textContent = getTranslation(lang, 'resetDescription');
    document.getElementById('resetPhoneLabel').textContent = getTranslation(lang, 'resetPhoneLabel');
    document.getElementById('resetSubmitBtn').textContent = getTranslation(lang, 'resetSubmitBtn');
    document.getElementById('backToLoginBtn').textContent = getTranslation(lang, 'backToLoginBtn');
    
    // Update terms text and link
    const termsLink = getTranslation(lang, 'termsLink');
    document.getElementById('termsText').innerHTML = 
        getTranslation(lang, 'termsText').replace('Terms of Service', `<a href="terms.html">${termsLink}</a>`);
}

function toggleResetMode(isReset) {
    const loginForm = document.getElementById('loginForm');
    const resetForm = document.getElementById('resetForm');
    const formTitle = document.getElementById('formTitle');

    if (isReset) {
        loginForm.style.display = 'none';
        resetForm.style.display = 'block';
        formTitle.textContent = getTranslation(currentLanguage, 'passwordResetTitle');
    } else {
        loginForm.style.display = 'block';
        resetForm.style.display = 'none';
        formTitle.textContent = getTranslation(currentLanguage, 'login');
        // Clear reset form
        document.getElementById('resetPhoneNumber').value = '';
    }
    
    // Clear message box
    hideMessage();
}

function showMessage(type, text) {
    const messageBox = document.getElementById('messageBox');
    messageBox.className = `message-box message-${type}`;
    messageBox.textContent = text;
    messageBox.style.display = 'block';
    
    // Auto-hide error messages after 5 seconds
    if (type === 'error') {
        setTimeout(hideMessage, 5000);
    }
}

function hideMessage() {
    const messageBox = document.getElementById('messageBox');
    messageBox.style.display = 'none';
}

async function handleLogin(event) {
    event.preventDefault();
    
    const phoneNumber = document.getElementById('phoneNumber').value;
    const password = document.getElementById('password').value;
    const submitBtn = document.getElementById('submitBtn');
    
    if (!phoneNumber || !password) {
        showMessage('error', 'Please fill in all fields');
        return;
    }

    // Disable button and show loading state
    submitBtn.disabled = true;
    const originalText = submitBtn.textContent;
    submitBtn.textContent = getTranslation(currentLanguage, 'sending');

    try {
        const response = await loginUser(phoneNumber, password);

        if (response.status === 'success') {
            showMessage('success', response.message);
            
            // Clear form
            document.getElementById('loginForm').reset();
            
            // Simulate redirect after 1.5 seconds
            setTimeout(() => {
                // In a real app, you'd store the auth token and redirect
                window.location.href = '/dashboard.html';
            }, 1500);
        } else {
            showMessage('error', response.message);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    } catch (error) {
        showMessage('error', 'An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function handlePasswordReset(event) {
    event.preventDefault();
    
    const phoneNumber = document.getElementById('resetPhoneNumber').value;
    const resetSubmitBtn = document.getElementById('resetSubmitBtn');
    
    if (!phoneNumber) {
        showMessage('error', 'Please enter your phone number');
        return;
    }

    // Disable button and show loading state
    resetSubmitBtn.disabled = true;
    const originalText = resetSubmitBtn.textContent;
    resetSubmitBtn.textContent = getTranslation(currentLanguage, 'sending');

    try {
        const response = await resetPassword(phoneNumber);

        if (response.status === 'success') {
            showMessage('success', response.message);
            
            // Redirect to login after 2 seconds
            setTimeout(() => {
                toggleResetMode(false);
                document.getElementById('resetForm').reset();
                resetSubmitBtn.disabled = false;
                resetSubmitBtn.textContent = originalText;
            }, 2000);
        } else {
            showMessage('error', response.message);
            resetSubmitBtn.disabled = false;
            resetSubmitBtn.textContent = originalText;
        }
    } catch (error) {
        showMessage('error', 'An error occurred. Please try again.');
        resetSubmitBtn.disabled = false;
        resetSubmitBtn.textContent = originalText;
    }
}
