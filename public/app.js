let token = '';
let isRunning = false;
let runInterval;

// Mock function to simulate an API login request
function demoLogin() {
    logAction('Logging in...');
    // In a real scenario, this fetches a JWT from /api/auth/login
    // Here we simulate success and bypass to dashboard for demo purposes
    setTimeout(() => {
        token = 'mock_jwt_token_for_demo';
        document.getElementById('auth-panel').style.display = 'none';
        document.getElementById('app-panel').style.display = 'block';
        logAction('Login successful.');
        fetchBalances();
    }, 1000);
}

// Simulates fetching balances from /api/spending/getBalance
function fetchBalances() {
    logAction('Fetching wallet balances...');
    // Simulating API response
    setTimeout(() => {
        document.getElementById('bal-sol').innerText = '1.50';
        document.getElementById('bal-coin').innerText = '500.00';
        document.getElementById('bal-token').innerText = '100.00';
        document.getElementById('bal-seed').innerText = '20.00';
        document.getElementById('energy').innerText = '10';
        logAction('Balances updated.');
    }, 800);
}

// Simulates sending GPS location / running
function toggleRun() {
    const btn = document.getElementById('run-btn');
    const status = document.getElementById('workout-status');

    if (!isRunning) {
        isRunning = true;
        btn.innerText = 'Stop Running';
        btn.style.backgroundColor = '#e74c3c';
        status.innerText = 'Status: Running 🏃...';
        logAction('Started workout. API Call: POST /api/workout/init');

        runInterval = setInterval(() => {
            logAction('Sending GPS ping... API Call: POST /api/workout/location');
            // Simulate earning coins
            let currentCoin = parseFloat(document.getElementById('bal-coin').innerText);
            document.getElementById('bal-coin').innerText = (currentCoin + 0.5).toFixed(2);
        }, 3000);
    } else {
        isRunning = false;
        clearInterval(runInterval);
        btn.innerText = 'Start Running';
        btn.style.backgroundColor = '#f39c12';
        status.innerText = 'Status: Idle';
        logAction('Stopped workout. API Call: POST /api/workout/finish');
        logAction('Workout final calculation complete.');
    }
}

function logAction(message) {
    const logs = document.getElementById('logs');
    const time = new Date().toLocaleTimeString();
    logs.innerHTML += `[${time}] ${message}<br>`;
    logs.scrollTop = logs.scrollHeight;
}
