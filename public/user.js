const API_BASE = '/api';
let currentToken = localStorage.getItem('token');
let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    initApp();

    document.getElementById('login-btn').addEventListener('click', showLoginForm);
    document.getElementById('logout-btn').addEventListener('click', logout);
    document.getElementById('login-form').addEventListener('submit', handleLogin);

    document.getElementById('prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchListings();
        }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        currentPage++;
        fetchListings();
    });
});

function initApp() {
    if (currentToken) {
        showUserView();
    } else {
        showGuestView();
    }
    fetchListings();
}

function showLoginForm() {
    document.getElementById('login-section').classList.remove('hidden');
    document.getElementById('marketplace-section').classList.add('hidden');
}

function showGuestView() {
    document.getElementById('login-btn').classList.remove('hidden');
    document.getElementById('user-info').classList.add('hidden');
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('marketplace-section').classList.remove('hidden');
}

function showUserView() {
    document.getElementById('login-btn').classList.add('hidden');
    document.getElementById('user-info').classList.remove('hidden');
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('marketplace-section').classList.remove('hidden');
    document.getElementById('user-email').textContent = 'Logged In'; // In real app, fetch user details
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');

    try {
        const response = await fetch(`${API_BASE}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (response.ok && data.token) {
            currentToken = data.token;
            localStorage.setItem('token', currentToken);
            errorEl.classList.add('hidden');
            showUserView();
            fetchListings(); // Refetch to show "Buy" buttons if applicable
        } else {
            errorEl.textContent = data.error || 'Login failed';
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = 'Network error';
        errorEl.classList.remove('hidden');
    }
}

function logout() {
    currentToken = null;
    localStorage.removeItem('token');
    showGuestView();
    fetchListings();
}

async function fetchListings() {
    const grid = document.getElementById('listings-grid');
    const loading = document.getElementById('loading');
    const errorEl = document.getElementById('error');

    grid.innerHTML = '';
    loading.classList.remove('hidden');
    errorEl.classList.add('hidden');

    try {
        const response = await fetch(`${API_BASE}/marketplace/listing?page=${currentPage}&limit=12`);
        const data = await response.json();

        loading.classList.add('hidden');

        if (response.ok && data.items) {
            renderListings(data.items);
            document.getElementById('page-info').textContent = `Page ${data.page}`;
            document.getElementById('prev-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = data.items.length < data.limit;
        } else {
            throw new Error('Failed to fetch listings');
        }
    } catch (err) {
        loading.classList.add('hidden');
        errorEl.classList.remove('hidden');
    }
}

function renderListings(listings) {
    const grid = document.getElementById('listings-grid');
    grid.innerHTML = '';

    if (listings.length === 0) {
        grid.innerHTML = '<p class="col-span-full text-center text-gray-500">No active listings found.</p>';
        return;
    }

    listings.forEach(listing => {
        const card = document.createElement('div');
        card.className = 'bg-white rounded shadow-md overflow-hidden p-4 flex flex-col justify-between';

        const content = `
            <div>
                <h3 class="text-xl font-bold mb-2 text-gray-800">Item ID: ${listing.item_id.substring(0, 8)}...</h3>
                <p class="text-gray-600 mb-1">Price: <span class="font-bold text-blue-600">${listing.price} ${listing.currency}</span></p>
                <p class="text-sm text-gray-500 mb-4">Seller: ${listing.seller_id.substring(0, 8)}...</p>
            </div>
        `;

        card.innerHTML = content;

        if (currentToken) {
            const buyBtn = document.createElement('button');
            buyBtn.className = 'w-full bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-200';
            buyBtn.textContent = 'Buy Now';
            buyBtn.onclick = () => buyItem(listing._id);
            card.appendChild(buyBtn);
        } else {
             const loginBtn = document.createElement('button');
             loginBtn.className = 'w-full bg-gray-300 cursor-not-allowed text-gray-600 font-bold py-2 px-4 rounded';
             loginBtn.textContent = 'Login to Buy';
             loginBtn.disabled = true;
             card.appendChild(loginBtn);
        }

        grid.appendChild(card);
    });
}

async function buyItem(listingId) {
    if (!confirm('Are you sure you want to buy this item?')) return;

    try {
        const response = await fetch(`${API_BASE}/marketplace/listing/buy/${listingId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${currentToken}`
            }
        });

        const data = await response.json();

        if (response.ok) {
            alert('Purchase successful!');
            fetchListings(); // Refresh grid
        } else {
            alert(data.error || 'Failed to purchase item');
        }
    } catch (err) {
        alert('Network error during purchase');
    }
}