// Global JavaScript functions for IMS

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    const dataTables = document.querySelectorAll('.datatable');
    if (dataTables.length > 0) {
        dataTables.forEach(table => {
            new DataTable(table, {
                responsive: true,
                language: {
                    search: "",
                    searchPlaceholder: "Search..."
                }
            });
        });
    }

    // Initialize Select2 for any select elements with the select2 class
    const select2Elements = document.querySelectorAll('.select2');
    if (select2Elements.length > 0) {
        $(select2Elements).each(function() {
            $(this).select2({
                theme: 'bootstrap-5',
                dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : document.body,
                width: '100%'
            });
        });
    }

    // Enable tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (tooltipTriggerList.length > 0) {
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    // Toggle password visibility
    const togglePassword = document.querySelector('#togglePassword');
    if (togglePassword) {
        const passwordInput = document.querySelector('#password');
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
    
    // File input preview (for product images)
    const imageInput = document.querySelector('#image');
    if (imageInput) {
        const previewContainer = document.querySelector('#imagePreview');
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" class="img-fluid img-thumbnail" style="max-height: 200px;">`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // POS System - Add to cart functionality
    const productCards = document.querySelectorAll('.product-card');
    if (productCards.length > 0) {
        productCards.forEach(card => {
            card.addEventListener('click', function() {
                const productId = this.dataset.id;
                const productName = this.dataset.name;
                const productPrice = parseFloat(this.dataset.price);
                
                addToCart(productId, productName, productPrice, this.dataset.maxstock);
                updateCartDisplay();
            });
        });
    }

    // POS System - Remove from cart
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-from-cart')) {
            const productId = e.target.dataset.id;
            removeFromCart(productId);
            updateCartDisplay();
        }
    });

    // POS System - Update quantity
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.classList.contains('decrease-qty') || e.target.classList.contains('increase-qty'))) {
            const productId = e.target.dataset.id;
            const action = e.target.classList.contains('decrease-qty') ? 'decrease' : 'increase';
            updateQuantity(productId, action, this.dataset.maxstock);
            updateCartDisplay();
        }
    });

    // Checkout form
    const checkoutForm = document.querySelector('#checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            if (getCartItems().length === 0) {
                e.preventDefault();
                alert('Your cart is empty. Please add items before checkout.');
            }
        });
    }

    // Print receipt
    const printReceiptBtn = document.querySelector('#print-receipt');
    if (printReceiptBtn) {
        printReceiptBtn.addEventListener('click', function() {
            window.print();
        });
    }
});

// POS Cart Functions
function getCartItems() {
    return JSON.parse(localStorage.getItem('cart') || '[]');
}

function saveCartItems(items) {
    localStorage.setItem('cart', JSON.stringify(items));
}

function addToCart(id, name, price, maxStock) {
    const cart = getCartItems();
    const existingItem = cart.find(item => item.id === id);
    
    if (existingItem) {
        // Check stock limits before increasing quantity
        if (existingItem.quantity >= maxStock) {
            showStockWarning(name, maxStock);
            return false;
        }
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: id,
            name: name,
            price: price,
            quantity: 1
        });
    }
    
    saveCartItems(cart);
    return true;
}

function showStockWarning(productName, stockAvailable) {
    const warningDiv = document.createElement('div');
    warningDiv.className = 'alert alert-warning alert-dismissible fade show mt-2';
    warningDiv.setAttribute('role', 'alert');
    warningDiv.innerHTML = `
        <strong>Stock limit reached!</strong> Only ${stockAvailable} unit(s) of "${productName}" available.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Find the container to show the warning
    const container = document.querySelector('.pos-container');
    if (container) {
        container.prepend(warningDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(warningDiv);
            alert.close();
        }, 5000);
    }
}

function removeFromCart(id) {
    const cart = getCartItems();
    const updatedCart = cart.filter(item => item.id !== id);
    saveCartItems(updatedCart);
}

function updateQuantity(id, action, maxStock) {
    const cart = getCartItems();
    const item = cart.find(item => item.id === id);
    
    if (item) {
        if (action === 'increase') {
            // Check stock limit before increasing
            if (item.quantity >= maxStock) {
                showStockWarning(item.name, maxStock);
                return false;
            }
            item.quantity += 1;
        } else if (action === 'decrease') {
            item.quantity -= 1;
            if (item.quantity <= 0) {
                return removeFromCart(id);
            }
        }
        saveCartItems(cart);
        return true;
    }
    return false;
}

function updateCartDisplay() {
    const cartItemsContainer = document.querySelector('#cart-items');
    const cartTotalElement = document.querySelector('#cart-total');
    const cartItemsInput = document.querySelector('#cart-items-input');
    const cartItems = getCartItems();
    
    if (!cartItemsContainer) return;
    
    // Clear cart container
    cartItemsContainer.innerHTML = '';
    
    // Calculate total
    let total = 0;
    
    // Add items to cart
    cartItems.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        itemElement.innerHTML = `
            <div class="cart-item-details">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-price">$${item.price.toFixed(2)} x ${item.quantity}</div>
            </div>
            <div class="cart-item-quantity">
                <button type="button" class="btn btn-sm btn-outline-secondary decrease-qty" data-id="${item.id}">-</button>
                <span>${item.quantity}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary increase-qty" data-id="${item.id}" data-maxstock="${getProductMaxStock(item.id)}">+</button>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-from-cart" data-id="${item.id}">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        cartItemsContainer.appendChild(itemElement);
    });
    
    // Update total price
    cartTotalElement.textContent = formatCurrency(total);
    
    // Update cart input for form submission
    if (cartItemsInput) {
        cartItemsInput.value = JSON.stringify(cartItems);
    }
    
    // Enable/disable checkout button
    const checkoutBtn = document.querySelector('#checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.disabled = cartItems.length === 0;
    }
}

// Helper function to get a product's max stock from the product grid
function getProductMaxStock(productId) {
    const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
    return productCard ? productCard.dataset.maxstock : 0;
}

// Clear cart function
function clearCart() {
    localStorage.removeItem('cart');
    updateCartDisplay();
}

// Format currency helper
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Export to Excel (CSV) function
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Skip columns with data-export="false"
            if (cols[j].dataset.export === 'false') continue;
            
            // Replace any commas in the cell with a space to avoid CSV formatting issues
            let data = cols[j].innerText.replace(/,/g, ' ');
            // Wrap in quotes to handle any other special characters
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    
    // Create download link
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    // Add to DOM, click and remove
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
} 