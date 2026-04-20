<!-- Reservations Section -->
<section id="reservations" class="admin-section">
    <div class="section-header">
        <h2>Reservations Management</h2>
        <div class="reservation-filters">
            <input type="date" id="reservationDate" onchange="filterReservations()">
            <select id="reservationStatus" onchange="filterReservations()">
                <option value="all">All Status</option>
                <option value="confirmed">Confirmed</option>
                <option value="pending">Pending</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>
    <div class="reservations-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Guests</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="reservationsTableBody">
                <!-- Reservations will be populated here via admin-script.js -->
            </tbody>
        </table>
    </div>
</section>
