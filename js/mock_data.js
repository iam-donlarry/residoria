/**
 * Mock Data Service for Estate Management System
 * Simulates a backend database and API latency
 */

// Initial Data
const MOCK_DB = {
    // 1. Street Hierarchy
    streets: [
        { id: 1, name: 'Palm Avenue', description: 'Main residential lane' },
        { id: 2, name: 'Oak Lane', description: 'Commercial and Mixed use' }
    ],
    // 2. Building Hierarchy
    buildings: [
        // Multi-unit: Contains multiple flats
        { id: 1, streetId: 1, name: 'Sunrise Apartments', type: 'Apartment', isSingleUnit: false, totalFloors: 5 },
        // Single-unit: The building IS the flat
        { id: 2, streetId: 1, name: 'Villa 24', type: 'Villa', isSingleUnit: true, totalFloors: 2 },
        { id: 3, streetId: 2, name: 'Tech Park Plaza', type: 'Commercial', isSingleUnit: false, totalFloors: 10 }
    ],
    // 3. Flat Hierarchy (Units within buildings or standalone villas)
    flats: [
        // Flats in Sunrise Apartments (buildingId: 1)
        { id: 101, buildingId: 1, flatNumber: '101', floor: 1, bedrooms: 2, bathrooms: 2, areaSqFt: 1200, ownerId: 1 },
        { id: 102, buildingId: 1, flatNumber: '102', floor: 1, bedrooms: 3, bathrooms: 2, areaSqFt: 1500, ownerId: 2 },
        { id: 201, buildingId: 1, flatNumber: '201', floor: 2, bedrooms: 2, bathrooms: 2, areaSqFt: 1200, ownerId: 3 },
        // Villa 24 (buildingId: 2) - treated as a single flat
        { id: 2401, buildingId: 2, flatNumber: 'Villa 24', floor: 0, bedrooms: 4, bathrooms: 3, areaSqFt: 3000, ownerId: 4 },
        // Flats in Tech Park Plaza (buildingId: 3) - commercial units
        { id: 301, buildingId: 3, flatNumber: 'Unit 301', floor: 3, type: 'Office', areaSqFt: 800, ownerId: 5 },
        { id: 302, buildingId: 3, flatNumber: 'Unit 302', floor: 3, type: 'Retail', areaSqFt: 1000, ownerId: 6 }
    ],
    // 4. Users (Residents, Owners, Admins)
    users: [
        { id: 1, name: 'Alice Smith', email: 'alice@example.com', password: 'password123', role: 'owner', flatId: 101 },
        { id: 2, name: 'Bob Johnson', email: 'bob@example.com', password: 'password123', role: 'owner', flatId: 102 },
        { id: 3, name: 'Charlie Brown', email: 'charlie@example.com', password: 'password123', role: 'resident', flatId: 201 },
        { id: 4, name: 'Diana Prince', email: 'diana@example.com', password: 'password123', role: 'owner', flatId: 2401 },
        { id: 5, name: 'Eve Adams', email: 'eve@example.com', password: 'password123', role: 'owner', flatId: 301 },
        { id: 6, name: 'Frank White', email: 'frank@example.com', password: 'password123', role: 'owner', flatId: 302 },
        { id: 7, name: 'Admin User', email: 'admin@example.com', password: 'adminpassword', role: 'admin' }
    ],
    // 5. Notices/Announcements
    notices: [
        { id: 1, title: 'Water Supply Interruption', content: 'Water supply will be interrupted on Oct 27, 9 AM - 1 PM.', date: '2023-10-25' },
        { id: 2, title: 'Community Meeting', content: 'Monthly meeting at the clubhouse at 6 PM.', date: '2023-10-28' }
    ]
};

// Initialize LocalStorage if empty
if (!localStorage.getItem('estate_db')) {
    localStorage.setItem('estate_db', JSON.stringify(MOCK_DB));
}

// Data Access Layer
const DB = {
    get: () => JSON.parse(localStorage.getItem('estate_db')),
    save: (data) => localStorage.setItem('estate_db', JSON.stringify(data)),

    // Auth Simulation
    login: (email, password) => {
        const db = DB.get();
        const user = db.users.find(u => u.email === email && u.password === password);
        if (user) {
            localStorage.setItem('estate_current_user', JSON.stringify(user));
            return { success: true, user };
        }
        return { success: false, message: 'Invalid credentials' };
    },

    getCurrentUser: () => {
        return JSON.parse(localStorage.getItem('estate_current_user'));
    },

    logout: () => {
        localStorage.removeItem('estate_current_user');
        window.location.href = '/Estate/index.html';
    }
};

// Expose globally
window.EstateService = DB;
