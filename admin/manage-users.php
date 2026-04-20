<!-- Customer Management Section -->
<section id="customers" class="admin-section">
    <div class="section-header">
        <h2>Customer Management</h2>
        <button class="btn btn-primary" onclick="exportCustomers()">
            <i class="fas fa-download"></i> Export Data
        </button>
    </div>
    
    <div class="customers-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                    <th>Last Order</th>
                </tr>
            </thead>
            <tbody id="customersTableBody">
                <!-- Customer data will be populated here via admin-script.js -->
            </tbody>
        </table>
    </div>
</section>
