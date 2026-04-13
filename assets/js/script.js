// Menu Category Filtering
document.addEventListener('DOMContentLoaded', function () {
    // Ensure all modals are hidden on page load
    const onlineModal = document.getElementById('onlineOrderModal');
    const offlineModal = document.getElementById('offlineOrderModal');
    const orderModal = document.getElementById('orderModal');

    if (onlineModal) onlineModal.classList.remove('active');
    if (offlineModal) offlineModal.classList.remove('active');
    if (orderModal) orderModal.classList.remove('active');

    // Initialize order modal
    initializeOrderModal();
    updateOrderDisplay();

    // Initialize search bar
    initializeSearchBar();

    // Only run menu filtering if on menu page
    const categoryButtons = document.querySelectorAll('.category-btn');
    if (categoryButtons.length > 0) {
        categoryButtons.forEach(button => {
            button.addEventListener('click', function () {
                // Update active button
                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');

                const category = this.getAttribute('data-category');
                filterMenuItems(category);
            });
        });
    }

    // Form Submission
    // Form Submission
    // const reservationForm = document.getElementById('reservationForm');
    // if (reservationForm) {
    //     reservationForm.addEventListener('submit', function(e) {
    //         // Let PHP handle the submission directly
    //     });

    //     // Set minimum date for reservation (today)
    //     const today = new Date().toISOString().split('T')[0];
    //     document.getElementById('date').setAttribute('min', today);
    // }

    const reservationForm = document.getElementById('reservationForm');
    if (reservationForm) {
        // Set minimum date for reservation (today)
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date').setAttribute('min', today);
    }

    // Header background on scroll
    window.addEventListener('scroll', function () {
        const header = document.querySelector('header');
        if (window.scrollY > 100) {
            header.style.backgroundColor = 'rgba(10, 10, 10, 0.98)';
        } else {
            header.style.backgroundColor = 'rgba(10, 10, 10, 0.95)';
        }
    });

    // Animation for dish cards and menu items on scroll
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Apply animation to dish cards and menu items
    document.querySelectorAll('.dish-card, .menu-item').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(card);
    });
    // Reveal hero title
    const heroTitle = document.querySelector('.hero-title');
    if (heroTitle) {
        heroTitle.style.opacity = '0';
        heroTitle.style.transform = 'translateY(10px)';
        heroTitle.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(heroTitle);
    }

    // Create mobile nav toggle and back-to-top
    createNavToggle();
    createBackToTop();
    // Smooth scroll for in-page anchors
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href').slice(1);
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                e.preventDefault();
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

function filterMenuItems(category) {
    const menuItems = document.querySelectorAll('.professional-menu-card, .card, .menu-item');

    menuItems.forEach(item => {
        if (category === 'all' || item.getAttribute('data-category') === category) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Initialize Search Bar
function initializeSearchBar() {
    const searchInput = document.getElementById('searchInput');

    if (searchInput) {
        searchInput.addEventListener('keyup', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const menuItems = document.querySelectorAll('.professional-menu-card, .card, .menu-item');

            menuItems.forEach(item => {
                let itemName = "", itemDescription = "", itemPrice = "";

                if (item.classList.contains('professional-menu-card')) {
                    const nameEl = item.querySelector('.dish-name');
                    const descEl = item.querySelector('.dish-description');
                    const priceEl = item.querySelector('.price');

                    if (nameEl) itemName = nameEl.textContent.toLowerCase();
                    if (descEl) itemDescription = descEl.textContent.toLowerCase();
                    if (priceEl) itemPrice = priceEl.textContent.toLowerCase();
                } else if (item.classList.contains('card')) {
                    const nameEl = item.querySelector('.card-content h2');
                    const descEl = item.querySelector('.card-content .desc');
                    const priceEl = item.querySelector('.price');

                    if (nameEl) itemName = nameEl.textContent.toLowerCase();
                    if (descEl) itemDescription = descEl.textContent.toLowerCase();
                    if (priceEl) itemPrice = priceEl.textContent.toLowerCase();
                } else {
                    const nameEl = item.querySelector('.menu-item-content h4');
                    const descEl = item.querySelector('.menu-item-content p');
                    const priceEl = item.querySelector('.menu-item-price');

                    if (nameEl) itemName = nameEl.textContent.toLowerCase();
                    if (descEl) itemDescription = descEl.textContent.toLowerCase();
                    if (priceEl) itemPrice = priceEl.textContent.toLowerCase();
                }

                if (itemName.includes(searchTerm) || itemDescription.includes(searchTerm) || itemPrice.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
}

// Search Menu Items
function searchMenuItems(searchTerm) {
    const menuItems = document.querySelectorAll('.menu-item');

    menuItems.forEach(item => {
        const itemName = item.querySelector('.menu-item-content h4').textContent.toLowerCase();
        const itemDescription = item.querySelector('.menu-item-content p').textContent.toLowerCase();
        const itemPrice = item.querySelector('.menu-item-price').textContent.toLowerCase();

        if (itemName.includes(searchTerm) || itemDescription.includes(searchTerm) || itemPrice.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Helper function to close all modals
function closeAllModals() {
    // Close all order modals
    const modals = document.querySelectorAll('.order-modal');
    modals.forEach(modal => {
        modal.classList.remove('active');
    });

    // Remove any order type modals
    const orderTypeModals = document.querySelectorAll('.order-type-modal');
    orderTypeModals.forEach(modal => {
        modal.remove();
    });
}

// Initialize Order Modal
function initializeOrderModal() {
    const orderBtn = document.getElementById('orderBtn');
    const orderModal = document.getElementById('orderModal');
    const closeOrderBtn = document.getElementById('closeOrderBtn');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const clearOrderBtn = document.getElementById('clearOrderBtn');

    if (orderBtn) {
        orderBtn.addEventListener('click', function () {
            orderModal.classList.add('active');
            updateOrderDisplay();
        });
    }

    if (closeOrderBtn) {
        closeOrderBtn.addEventListener('click', function () {
            orderModal.classList.remove('active');
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
            if (orders.length === 0) {
                alert('Your order is empty!');
                return;
            }

            // Hide main order modal first
            const orderModal = document.getElementById('orderModal');
            if (orderModal) {
                orderModal.classList.remove('active');
            }

            // Remove any existing order type modal
            const existingOrderTypeModal = document.querySelector('.order-type-modal');
            if (existingOrderTypeModal) {
                existingOrderTypeModal.remove();
            }

            // Create order type selection modal
            const orderTypeModal = document.createElement('div');
            orderTypeModal.className = 'order-type-modal';
            orderTypeModal.innerHTML = `
                <div class="order-type-modal-content">
                    <div class="order-modal-header">
                        <h2>Choose Order Type</h2>
                        <button class="close-btn" id="closeOrderTypeBtn">&times;</button>
                    </div>
                    <div class="order-type-options">
                        <div class="order-type-option" data-type="online">
                            <h3>🚚 Online Order</h3>
                            <p>Delivered to your location</p>
                        </div>
                        <div class="order-type-option" data-type="offline">
                            <h3>🏪 Offline Order</h3>
                            <p>Pickup at restaurant</p>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(orderTypeModal);

            // Force reflow and show modal
            orderTypeModal.offsetHeight;
            orderTypeModal.classList.add('active');

            // Handle close button
            const closeBtn = orderTypeModal.querySelector('#closeOrderTypeBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    orderTypeModal.remove();
                });
            }

            // Handle clicking outside modal
            orderTypeModal.addEventListener('click', function (e) {
                if (e.target === orderTypeModal) {
                    orderTypeModal.remove();
                }
            });

            // Handle order type selection
            const orderOptions = orderTypeModal.querySelectorAll('.order-type-option');

            orderOptions.forEach(option => {
                option.addEventListener('click', function () {
                    const orderType = this.getAttribute('data-type');

                    // Calculate total
                    const orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
                    let total = 0;
                    orders.forEach(order => {
                        total += order.price * order.quantity;
                    });

                    // Remove order type modal
                    orderTypeModal.remove();

                    // Show appropriate modal based on selection
                    setTimeout(() => {
                        if (orderType === 'online') {
                            const onlineOrderTotal = document.getElementById('onlineOrderTotal');
                            if (onlineOrderTotal) {
                                onlineOrderTotal.textContent = `TK ${total.toLocaleString()}`;
                            }

                            const onlineModal = document.getElementById('onlineOrderModal');
                            if (onlineModal) {
                                onlineModal.classList.add('active');
                                // Fetch and populate branches
                                fetchBranchesForOrder();
                            }

                        } else if (orderType === 'offline') {
                            const offlineOrderTotal = document.getElementById('offlineOrderTotal');
                            if (offlineOrderTotal) {
                                offlineOrderTotal.textContent = `TK ${total.toLocaleString()}`;
                            }

                            const offlineModal = document.getElementById('offlineOrderModal');
                            if (offlineModal) {
                                offlineModal.classList.add('active');
                            }
                        }
                    }, 200);
                });
            });
        });
    }

    if (clearOrderBtn) {
        clearOrderBtn.addEventListener('click', function () {
            if (confirm('Are you sure you want to clear your order?')) {
                localStorage.removeItem('felicianoOrders');
                updateOrderDisplay();
            }
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === orderModal) {
            orderModal.classList.remove('active');
        }
    });

    // Initialize online order modal
    initializeOnlineOrderModal();

    // Initialize offline order modal
    initializeOfflineOrderModal();
}

// Initialize Online Order Modal
function initializeOnlineOrderModal() {
    const onlineOrderModal = document.getElementById('onlineOrderModal');
    const closeOnlineOrderBtn = document.getElementById('closeOnlineOrderBtn');
    const cancelOnlineOrderBtn = document.getElementById('cancelOnlineOrderBtn');
    const confirmOnlineOrderBtn = document.getElementById('confirmOnlineOrderBtn');

    if (closeOnlineOrderBtn) {
        closeOnlineOrderBtn.addEventListener('click', function () {
            closeAllModals();
        });
    }

    if (cancelOnlineOrderBtn) {
        cancelOnlineOrderBtn.addEventListener('click', function () {
            closeAllModals();
        });
    }

    if (confirmOnlineOrderBtn) {
        confirmOnlineOrderBtn.addEventListener('click', function () {
            const customerName = document.getElementById('customerName').value;
            const customerPhone = document.getElementById('customerPhone').value;
            const customerEmail = document.getElementById('customerEmail').value;
            const deliveryAddress = document.getElementById('deliveryAddress').value;
            const deliveryTime = document.getElementById('deliveryTime').value;
            const specialInstructions = document.getElementById('specialInstructions').value;
            const branchId = document.getElementById('orderBranch').value;

            if (!customerName || !customerPhone || !customerEmail || !deliveryAddress || !branchId) {
                alert('Please fill in all required fields, including selecting a branch!');
                return;
            }

            const orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];

            const orderData = {
                orderType: 'online',
                customerName: customerName,
                customerPhone: customerPhone,
                customerEmail: customerEmail,
                deliveryAddress: deliveryAddress,
                deliveryTime: deliveryTime,
                specialInstructions: specialInstructions,
                branchId: branchId,
                orders: orders
            };

            // Robust path resolution for submit_order.php
            const getFetchUrl = () => {
                const path = window.location.pathname;
                const encodedProject = '/Feliciano%20%20Restaurant/';
                const rawProject = '/Feliciano  Restaurant/';
                
                if (path.includes(encodedProject)) {
                    return path.substring(0, path.indexOf(encodedProject) + encodedProject.length) + 'submit_order.php';
                }
                if (path.includes(rawProject)) {
                    return path.substring(0, path.indexOf(rawProject) + rawProject.length) + 'submit_order.php';
                }
                return path.includes('/pages/') ? '../submit_order.php' : 'submit_order.php';
            };
            
            const fetchUrl = getFetchUrl();
            console.log('Target API URL:', fetchUrl);

            // Send to backend
            fetch(fetchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            })
                .then(async response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        const text = await response.text();
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 100));
                    }
                })
                .then(data => {
                    if (data.status === 'success' || data.success === true) {
                        alert('Thank you for your online order! Order ID: ' + data.orderId);
                        localStorage.removeItem('felicianoOrders');
                        updateOrderDisplay();
                        closeAllModals();
                    } else {
                        alert('Error placing order: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. ' + error.message);
                });
        });
    }

    // Close modal when clicking outside
    if (onlineOrderModal) {
        onlineOrderModal.addEventListener('click', function (event) {
            if (event.target === onlineOrderModal) {
                onlineOrderModal.classList.remove('active');
            }
        });
    }
}

// Initialize Offline Order Modal
function initializeOfflineOrderModal() {
    const offlineOrderModal = document.getElementById('offlineOrderModal');
    const closeOfflineOrderBtn = document.getElementById('closeOfflineOrderBtn');
    const cancelOfflineOrderBtn = document.getElementById('cancelOfflineOrderBtn');
    const confirmOfflineOrderBtn = document.getElementById('confirmOfflineOrderBtn');

    if (closeOfflineOrderBtn) {
        closeOfflineOrderBtn.addEventListener('click', function () {
            closeAllModals();
        });
    }

    if (cancelOfflineOrderBtn) {
        cancelOfflineOrderBtn.addEventListener('click', function () {
            closeAllModals();
        });
    }

    if (confirmOfflineOrderBtn) {
        confirmOfflineOrderBtn.addEventListener('click', function () {
            const tableName = document.getElementById('tableName').value;
            const personCount = document.getElementById('personCount').value;
            const customerName = document.getElementById('customerNameOffline').value;
            const customerPhone = document.getElementById('customerPhoneOffline').value;
            const specialInstructions = document.getElementById('specialInstructionsOffline').value;

            if (!tableName || !personCount || !customerName || !customerPhone) {
                alert('Please fill in all required fields!');
                return;
            }

            const orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];

            const orderData = {
                orderType: 'offline',
                customerName: customerName,
                customerPhone: customerPhone,
                customerEmail: '', // Not required for offline orders
                tableNumber: tableName,
                specialInstructions: specialInstructions,
                orders: orders
            };

            // Robust path resolution for submit_order.php
            const getFetchUrl = () => {
                const path = window.location.pathname;
                const encodedProject = '/Feliciano%20%20Restaurant/';
                const rawProject = '/Feliciano  Restaurant/';
                
                if (path.includes(encodedProject)) {
                    return path.substring(0, path.indexOf(encodedProject) + encodedProject.length) + 'submit_order.php';
                }
                if (path.includes(rawProject)) {
                    return path.substring(0, path.indexOf(rawProject) + rawProject.length) + 'submit_order.php';
                }
                return path.includes('/pages/') ? '../submit_order.php' : 'submit_order.php';
            };
            
            const fetchUrl = getFetchUrl();
            console.log('Target API URL:', fetchUrl);

            // Send to backend
            fetch(fetchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            })
                .then(async response => {
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        const text = await response.text();
                        throw new Error("Server returned non-JSON response: " + text.substring(0, 100));
                    }
                })
                .then(data => {
                    if (data.status === 'success' || data.success === true) {
                        alert('Thank you for your offline order! Please wait at table ' + tableName + '. Order ID: ' + data.orderId);
                        localStorage.removeItem('felicianoOrders');
                        updateOrderDisplay();
                        closeAllModals();
                    } else {
                        alert('Error placing order: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. ' + error.message);
                });
        });
    }

    // Close modal when clicking outside
    if (offlineOrderModal) {
        offlineOrderModal.addEventListener('click', function (event) {
            if (event.target === offlineOrderModal) {
                offlineOrderModal.classList.remove('active');
            }
        });
    }
}

// Update Order Display
function updateOrderDisplay() {
    let orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
    
    // Filter out any malformed items (e.g., from previous bugs)
    const validOrders = orders.filter(item => item && item.id && !isNaN(parseFloat(item.price)));
    if (orders.length !== validOrders.length) {
        orders = validOrders;
        localStorage.setItem('felicianoOrders', JSON.stringify(orders));
    }

    const orderList = document.getElementById('orderList');
    const orderTotal = document.getElementById('orderTotal');
    const orderCount = document.getElementById('orderCount');

    if (!orderList || !orderTotal || !orderCount) return;

    // Update count badge
    const totalItems = orders.reduce((sum, order) => sum + (parseInt(order.quantity) || 0), 0);
    orderCount.textContent = totalItems;

    // Update order list
    if (orders.length === 0) {
        orderList.innerHTML = '<p class="empty-order">No items in your order yet</p>';
        orderTotal.textContent = 'TK 0';
        return;
    }

    let total = 0;
    orderList.innerHTML = '';

    orders.forEach((order, index) => {
        const itemPrice = parseFloat(order.price) || 0;
        const itemQty = parseInt(order.quantity) || 0;
        const itemTotal = itemPrice * itemQty;
        total += itemTotal;

        const orderItem = document.createElement('div');
        orderItem.className = 'order-item';
        orderItem.innerHTML = `
            <div class="order-item-info">
                <div class="order-item-name">${order.name || 'Unknown Item'}</div>
                <div class="order-item-price">TK ${itemPrice.toLocaleString()} x ${itemQty}</div>
            </div>
            <div class="order-item-controls">
                <button class="qty-btn" onclick="decreaseQuantity(${index})">-</button>
                <div class="qty-display">${itemQty}</div>
                <button class="qty-btn" onclick="increaseQuantity(${index})">+</button>
                <button class="remove-btn" onclick="removeFromOrder(${index})">Remove</button>
            </div>
        `;
        orderList.appendChild(orderItem);
    });

    orderTotal.textContent = `TK ${total.toLocaleString()}`;
}

// Order Management Function
function addToOrder(id, itemName, itemPrice) {
    if (!id || !itemName) {
        console.error('Invalid item data:', { id, itemName, itemPrice });
        return;
    }

    // Get existing orders from localStorage
    let orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];

    // Check if item already exists in orders
    const existingItem = orders.find(order => order.id === id);

    if (existingItem) {
        // Increase quantity if item already in order
        existingItem.quantity = (parseInt(existingItem.quantity) || 0) + 1;
    } else {
        // Add new item to order
        orders.push({
            id: id,
            name: itemName,
            price: parseFloat(itemPrice) || 0,
            quantity: 1
        });
    }

    // Save updated orders to localStorage
    localStorage.setItem('felicianoOrders', JSON.stringify(orders));

    // Show confirmation message
    alert(`${itemName} has been added to your order!`);

    // Update order display
    updateOrderDisplay();
}

// Increase quantity
function increaseQuantity(index) {
    let orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
    if (orders[index]) {
        orders[index].quantity += 1;
        localStorage.setItem('felicianoOrders', JSON.stringify(orders));
        updateOrderDisplay();
    }
}

// Decrease quantity
function decreaseQuantity(index) {
    let orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
    if (orders[index]) {
        if (orders[index].quantity > 1) {
            orders[index].quantity -= 1;
        } else {
            orders.splice(index, 1);
        }
        localStorage.setItem('felicianoOrders', JSON.stringify(orders));
        updateOrderDisplay();
    }
}

// Remove from order
function removeFromOrder(index) {
    let orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
    if (confirm(`Remove ${orders[index].name} from order?`)) {
        orders.splice(index, 1);
        localStorage.setItem('felicianoOrders', JSON.stringify(orders));
        updateOrderDisplay();
    }
}

// Function to update order count
function updateOrderCount() {
    const orders = JSON.parse(localStorage.getItem('felicianoOrders')) || [];
    const totalItems = orders.reduce((sum, order) => sum + order.quantity, 0);

    // You can use this to display order count in header if needed
    console.log(`Total items in order: ${totalItems}`);
}

// Create mobile nav toggle button and behavior
function createNavToggle() {
    const nav = document.querySelector('nav');
    if (!nav) return;
    const navList = nav.querySelector('.nav-links') || nav.querySelector('ul');
    if (!navList) return;

    // Avoid duplicate toggle
    if (nav.querySelector('.nav-toggle')) return;

    const toggle = document.createElement('button');
    toggle.className = 'nav-toggle';
    toggle.setAttribute('aria-label', 'Toggle navigation');
    toggle.innerHTML = '<span class="bar"></span>';
    nav.insertBefore(toggle, nav.firstChild);

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        navList.classList.toggle('open');
    });

    // Close when clicking a link
    navList.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navList.classList.remove('open')));

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!nav.contains(e.target) && navList.classList.contains('open')) {
            navList.classList.remove('open');
        }
    });
}

// Back to top button
function createBackToTop() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;

    window.addEventListener('scroll', function () {
        if (window.scrollY > 400) {
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    });

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Branch Fetching for Orders
async function fetchBranchesForOrder() {
    const branchSelect = document.getElementById('orderBranch');
    if (!branchSelect) return;

    // Robust path resolution for api-branches.php
    const getFetchUrl = () => {
        const path = window.location.pathname;
        const encodedProject = '/Feliciano%20%20Restaurant/';
        const rawProject = '/Feliciano  Restaurant/';
        
        let baseUrl = '';
        if (path.includes(encodedProject)) {
            baseUrl = path.substring(0, path.indexOf(encodedProject) + encodedProject.length);
        } else if (path.includes(rawProject)) {
            baseUrl = path.substring(0, path.indexOf(rawProject) + rawProject.length);
        } else {
            baseUrl = path.includes('/pages/') ? '../' : '';
        }
        return baseUrl + 'admin/api-branches.php?action=list';
    };

    try {
        const response = await fetch(getFetchUrl());
        const res = await response.json();
        if (res.success) {
            branchSelect.innerHTML = '<option value="">Select a branch near you</option>';
            res.data.forEach(branch => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = `${branch.name} - ${branch.location}`;
                branchSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching branches:', error);
    }
}