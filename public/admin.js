const API_BASE = '/api';
let currentAdminToken = localStorage.getItem('adminToken');
let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    initAdminApp();

    document.getElementById('login-btn').addEventListener('click', showLoginForm);
    document.getElementById('logout-btn').addEventListener('click', logoutAdmin);
    document.getElementById('login-form').addEventListener('submit', handleAdminLogin);
    document.getElementById('refresh-btn').addEventListener('click', () => fetchAdminListings(true));

    document.getElementById('prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchAdminListings();
        }
    });

    document.getElementById('next-page').addEventListener('click', () => {
        currentPage++;
        fetchAdminListings();
    });
});

function initAdminApp() {
    if (currentAdminToken) {
        showAdminDashboard();
        fetchAdminListings();
    } else {
        showGuestView();
    }
}

function showLoginForm() {
    document.getElementById('login-section').classList.remove('hidden');
    document.getElementById('dashboard-section').classList.add('hidden');
    document.getElementById('refresh-btn').classList.add('hidden');
    document.querySelector('.mt-4.flex').classList.add('hidden');
}

function showGuestView() {
    document.getElementById('login-btn').classList.remove('hidden');
    document.getElementById('user-info').classList.add('hidden');
    document.getElementById('login-section').classList.remove('hidden');
    document.getElementById('dashboard-section').classList.add('hidden');
    document.getElementById('refresh-btn').classList.add('hidden');
    document.querySelector('.mt-4.flex').classList.add('hidden');
}

function showAdminDashboard() {
    document.getElementById('login-btn').classList.add('hidden');
    document.getElementById('user-info').classList.remove('hidden');
    document.getElementById('login-section').classList.add('hidden');
    document.getElementById('dashboard-section').classList.remove('hidden');
    document.getElementById('refresh-btn').classList.remove('hidden');
    document.querySelector('.mt-4.flex').classList.remove('hidden');
}

async function handleAdminLogin(e) {
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
            // Note: In a real implementation, the backend should verify if the user has an 'admin' role
            // before issuing an admin token or allowing dashboard access.
            currentAdminToken = data.token;
            localStorage.setItem('adminToken', currentAdminToken);
            errorEl.classList.add('hidden');
            showAdminDashboard();
            fetchAdminListings(true);
        } else {
            errorEl.textContent = data.error || 'Login failed';
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = 'Network error';
        errorEl.classList.remove('hidden');
    }
}

function logoutAdmin() {
    currentAdminToken = null;
    localStorage.removeItem('adminToken');
    showGuestView();
}

async function fetchAdminListings(forceRefresh = false) {
    if (!currentAdminToken) return;

    const tableBody = document.getElementById('admin-listings-table');
    const loading = document.getElementById('loading');
    const errorEl = document.getElementById('error');

    tableBody.innerHTML = '';
    loading.classList.remove('hidden');
    errorEl.classList.add('hidden');

    try {
        // We use the same endpoint. In a real scenario, you might have a specific admin endpoint
        // to see all listings regardless of status (active, sold, cancelled).
        // Since caching is enabled on the backend, append a timestamp if force refresh is requested
        const timestamp = forceRefresh ? `&_t=${new Date().getTime()}` : '';
        const response = await fetch(`${API_BASE}/marketplace/listing?page=${currentPage}&limit=20${timestamp}`, {
            headers: {
                'Authorization': `Bearer ${currentAdminToken}`
            }
        });

        const data = await response.json();

        loading.classList.add('hidden');

        if (response.ok && data.items) {
            renderAdminTable(data.items);
            document.getElementById('page-info').textContent = `Page ${data.page}`;
            document.getElementById('prev-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = data.items.length < data.limit;
        } else {
            throw new Error('Failed to fetch listings');
        }
    } catch (err) {
        loading.classList.add('hidden');
        errorEl.classList.remove('hidden');
        if (err.message.includes('Unauthorized') || err.message.includes('Forbidden')) {
            logoutAdmin(); // Token might be expired
        }
    }
}

function renderAdminTable(listings) {
    const tableBody = document.getElementById('admin-listings-table');
    tableBody.innerHTML = '';

    if (listings.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" class="py-3 px-6 text-center">No listings found.</td></tr>';
        return;
    }

    listings.forEach(listing => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-200 hover:bg-gray-100';

        const content = `
            <td class="py-3 px-6 text-left whitespace-nowrap">
                <div class="flex items-center">
                    <span class="font-medium">${listing._id}</span>
                </div>
            </td>
            <td class="py-3 px-6 text-left">
                <div class="flex items-center">
                    <span>${listing.item_id}</span>
                </div>
            </td>
            <td class="py-3 px-6 text-left">
                <span>${listing.seller_id}</span>
            </td>
            <td class="py-3 px-6 text-center">
                <span class="bg-blue-200 text-blue-600 py-1 px-3 rounded-full text-xs font-bold">${listing.price} ${listing.currency}</span>
            </td>
            <td class="py-3 px-6 text-center">
                <span class="bg-green-200 text-green-600 py-1 px-3 rounded-full text-xs">${listing.status}</span>
            </td>
            <td class="py-3 px-6 text-center">
                <div class="flex item-center justify-center">
                    <button onclick="deleteListing('${listing._id}')" class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" title="Delete/Cancel Listing">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </td>
        `;

        row.innerHTML = content;
        tableBody.appendChild(row);
    });
}

async function deleteListing(listingId) {
    if (!confirm(`Are you sure you want to cancel listing ${listingId}?`)) return;

    try {
        const response = await fetch(`${API_BASE}/marketplace/listing/${listingId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${currentAdminToken}`
            }
        });

        const data = await response.json();

        if (response.ok) {
            alert('Listing removed successfully');
            fetchAdminListings(true);
        } else {
            alert(data.error || 'Failed to remove listing');
        }
    } catch (err) {
        alert('Network error during deletion');
    }
}