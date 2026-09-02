<!-- ===== DASHBOARD SECTION ===== -->
<section id="dashboard" class="admin-section active">

    <!-- ── Top Header ── -->
    <div class="db-top-header">
        <div>
            <h2 class="db-welcome">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>! 👋</h2>
            <p class="db-sub">Here's what's happening with your restaurant today.</p>
        </div>
        <div class="db-date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?= date('l, d F Y') ?>
        </div>
    </div>

    <!-- ── Row 1: 4 Primary KPI Cards ── -->
    <div class="db-kpi-row">
        <div class="db-kpi-card db-kpi-blue">
            <div class="db-kpi-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="db-kpi-body">
                <span class="db-kpi-label">Today's Orders</span>
                <span class="db-kpi-value" id="todayOrders">0</span>
                <span class="db-kpi-change positive" id="ordersChangePct"><i class="fas fa-arrow-up"></i> — vs yesterday</span>
            </div>
        </div>
        <div class="db-kpi-card db-kpi-green">
            <div class="db-kpi-icon"><i class="fas fa-bangladeshi-taka-sign"></i></div>
            <div class="db-kpi-body">
                <span class="db-kpi-label">Today's Revenue</span>
                <span class="db-kpi-value" id="todayRevenue">TK 0</span>
                <span class="db-kpi-change positive" id="revenueChangePct"><i class="fas fa-arrow-up"></i> — vs yesterday</span>
            </div>
        </div>
        <div class="db-kpi-card db-kpi-orange">
            <div class="db-kpi-icon"><i class="fas fa-clock"></i></div>
            <div class="db-kpi-body">
                <span class="db-kpi-label">Pending Orders</span>
                <span class="db-kpi-value" id="pendingOrders">0</span>
                <span class="db-kpi-change negative" id="pendingChangePct"><i class="fas fa-arrow-down"></i> — vs yesterday</span>
            </div>
        </div>
        <div class="db-kpi-card db-kpi-purple">
            <div class="db-kpi-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="db-kpi-body">
                <span class="db-kpi-label">Total Reservations</span>
                <span class="db-kpi-value" id="totalReservations">0</span>
                <span class="db-kpi-change positive" id="resvChangePct"><i class="fas fa-arrow-up"></i> — vs yesterday</span>
            </div>
        </div>
    </div>

    <!-- ── Row 2: 5 Secondary Stats ── -->
    <div class="db-sec-row">
        <div class="db-sec-card">
            <div class="db-sec-icon" style="color:#1abc9c;background:#e8fdf5"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="db-sec-value" id="completedOrders">0</div>
                <div class="db-sec-label">Completed Orders</div>
            </div>
        </div>
        <div class="db-sec-card">
            <div class="db-sec-icon" style="color:#e74c3c;background:#fef2f2"><i class="fas fa-times-circle"></i></div>
            <div>
                <div class="db-sec-value" id="cancelledOrders">0</div>
                <div class="db-sec-label">Cancelled Orders</div>
            </div>
        </div>
        <div class="db-sec-card">
            <div class="db-sec-icon" style="color:#f39c12;background:#fffbeb"><i class="fas fa-users"></i></div>
            <div>
                <div class="db-sec-value" id="totalCustomers">0</div>
                <div class="db-sec-label">Total Customers</div>
            </div>
        </div>
        <div class="db-sec-card">
            <div class="db-sec-icon" style="color:#3498db;background:#eff8ff"><i class="fas fa-utensils"></i></div>
            <div>
                <div class="db-sec-value" id="totalMenuItems">0</div>
                <div class="db-sec-label">Menu Items</div>
            </div>
        </div>
        <div class="db-sec-card">
            <div class="db-sec-icon" style="color:#9b59b6;background:#f5f0ff"><i class="fas fa-star"></i></div>
            <div>
                <div class="db-sec-value" id="pendingReviews">0</div>
                <div class="db-sec-label">Pending Reviews</div>
            </div>
        </div>
    </div>

    <!-- ── Row 3: Sales Overview + Kitchen & Delivery ── -->
    <div class="db-row3">
        <!-- Sales Overview -->
        <div class="db-card db-sales-card">
            <div class="db-card-head">
                <div>
                    <div class="db-card-title">Sales Overview</div>
                    <div class="db-card-sub">Total Revenue</div>
                    <div class="db-sales-total">TK <span id="weeklySalesTotal">0</span>
                        <span class="db-sales-change positive" id="salesOverviewChange"><i class="fas fa-arrow-up"></i> — last 7 days</span>
                    </div>
                </div>
                <select class="db-select" id="salesRangeSelect" onchange="loadWeeklySalesBar()">
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                </select>
            </div>
            <div style="position:relative;height:180px;margin-top:8px">
                <canvas id="salesOverviewChart"></canvas>
            </div>
        </div>

        <!-- Kitchen + Delivery -->
        <div class="db-col-right">
            <!-- Kitchen Status -->
            <div class="db-card">
                <div class="db-card-head">
                    <span class="db-card-title">Kitchen Status</span>
                    <a href="#" class="db-view-all" onclick="showSection('orders');return false;">View All</a>
                </div>
                <div class="db-kitchen-wrap">
                    <div class="db-donut-wrap">
                        <svg viewBox="0 0 100 100" class="db-donut-svg" id="kitchenDonutSvg">
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#e9ecef" stroke-width="12"/>
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#f39c12" stroke-width="12"
                                stroke-dasharray="0 239" stroke-dashoffset="0" stroke-linecap="round"
                                id="kDonutPending" style="transition:stroke-dasharray .6s ease"/>
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#3498db" stroke-width="12"
                                stroke-dasharray="0 239" stroke-dashoffset="0" stroke-linecap="round"
                                id="kDonutConfirmed" style="transition:stroke-dasharray .6s ease"/>
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#e67e22" stroke-width="12"
                                stroke-dasharray="0 239" stroke-dashoffset="0" stroke-linecap="round"
                                id="kDonutPreparing" style="transition:stroke-dasharray .6s ease"/>
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#27ae60" stroke-width="12"
                                stroke-dasharray="0 239" stroke-dashoffset="0" stroke-linecap="round"
                                id="kDonutReady" style="transition:stroke-dasharray .6s ease"/>
                        </svg>
                        <div class="db-donut-center">
                            <span class="db-donut-num" id="kitchenTotalNum">0</span>
                            <span class="db-donut-lbl">Total</span>
                        </div>
                    </div>
                    <div class="db-kitchen-legend" id="kitchenLegend">
                        <div class="db-legend-row"><span class="db-dot" style="background:#f39c12"></span>Pending <span class="db-legend-num" id="kLegPending">0</span></div>
                        <div class="db-legend-row"><span class="db-dot" style="background:#3498db"></span>Confirmed <span class="db-legend-num" id="kLegConfirmed">0</span></div>
                        <div class="db-legend-row"><span class="db-dot" style="background:#e67e22"></span>Preparing <span class="db-legend-num" id="kLegPreparing">0</span></div>
                        <div class="db-legend-row"><span class="db-dot" style="background:#27ae60"></span>Ready <span class="db-legend-num" id="kLegReady">0</span></div>
                    </div>
                </div>
            </div>

            <!-- Delivery Status -->
            <div class="db-card">
                <div class="db-card-head">
                    <span class="db-card-title">Delivery Status</span>
                    <a href="#" class="db-view-all" onclick="showSection('orders');return false;">View All</a>
                </div>
                <div class="db-delivery-row" id="dashDeliveryStatus">
                    <div class="db-delivery-box" style="background:#fef2f2;border-color:#fecaca">
                        <i class="fas fa-clock" style="color:#e74c3c"></i>
                        <span class="db-del-num" id="delAwaiting">0</span>
                        <span class="db-del-lbl">Awaiting Rider</span>
                    </div>
                    <div class="db-delivery-box" style="background:#fffbeb;border-color:#fde68a">
                        <i class="fas fa-motorcycle" style="color:#f59e0b"></i>
                        <span class="db-del-num" id="delOut">0</span>
                        <span class="db-del-lbl">Out for Delivery</span>
                    </div>
                    <div class="db-delivery-box" style="background:#f0fdf4;border-color:#bbf7d0">
                        <i class="fas fa-house-circle-check" style="color:#27ae60"></i>
                        <span class="db-del-num" id="delDelivered">0</span>
                        <span class="db-del-lbl">Delivered Today</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Row 4: Order Status + Popular Items + Low Stock ── -->
    <div class="db-row4">
        <!-- Order Status Overview -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title">Order Status Overview</span>
                <select class="db-select">
                    <option>This Week</option>
                    <option>This Month</option>
                </select>
            </div>
            <div id="orderStatusChart" class="db-os-list">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
            <div class="db-os-total">Total Orders <strong id="osTotalOrders">0</strong></div>
        </div>

        <!-- Today's Popular Items -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title">Today's Popular Items</span>
                <a href="#" class="db-view-all" onclick="showSection('menu-management');return false;">View All</a>
            </div>
            <div id="dashPopularItems" class="db-popular-list">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title"><i class="fas fa-triangle-exclamation" style="color:#f59e0b"></i> Low Stock Alert</span>
                <a href="#" class="db-view-all" onclick="showSection('inventory');return false;">View All</a>
            </div>
            <div id="lowStockList" class="db-stock-list">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>

    <!-- ── Row 5: Today vs Yesterday + Attendance + Recent Orders ── -->
    <div class="db-row5">
        <!-- Today vs Yesterday -->
        <div class="db-card">
            <div class="db-card-title" style="margin-bottom:12px">Today vs Yesterday</div>
            <div class="db-tvy-cards">
                <div class="db-tvy-box" style="background:#f0fdf4;border-color:#bbf7d0">
                    <div class="db-tvy-label">Revenue</div>
                    <div class="db-tvy-val green" id="cmpTodayRev">TK 0</div>
                    <div class="db-tvy-diff" id="cmpRevDiff"></div>
                </div>
                <div class="db-tvy-box" style="background:#eff6ff;border-color:#bfdbfe">
                    <div class="db-tvy-label">Orders</div>
                    <div class="db-tvy-val blue" id="cmpTodayOrders">0</div>
                    <div class="db-tvy-diff" id="cmpOrdersDiff"></div>
                </div>
                <div class="db-tvy-box" style="background:#fffbeb;border-color:#fde68a">
                    <div class="db-tvy-label">Avg Order</div>
                    <div class="db-tvy-val orange" id="cmpTodayAvg">TK 0</div>
                    <div class="db-tvy-diff" id="cmpAvgDiff"></div>
                </div>
            </div>
        </div>

        <!-- Today's Attendance -->
        <div class="db-card">
            <div class="db-card-title" style="margin-bottom:12px">Today's Attendance</div>
            <div id="attendanceSummary">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title">Recent Orders</span>
                <a href="#" class="db-view-all" onclick="showSection('orders');return false;">View All</a>
            </div>
            <div id="recentOrdersList" class="db-recent-list">
                <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>

    <!-- ── Row 6: Daily Revenue + Monthly Revenue Charts ── -->
    <div class="db-row6">
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title">Daily Revenue <span style="font-size:.75rem;color:#94a3b8;font-weight:400">(Last 7 Days)</span></span>
                <span class="db-chart-badge">Bar Chart</span>
            </div>
            <div style="position:relative;height:200px">
                <canvas id="dailyRevenueChart"></canvas>
            </div>
        </div>
        <div class="db-card">
            <div class="db-card-head">
                <span class="db-card-title">Monthly Revenue <span style="font-size:.75rem;color:#94a3b8;font-weight:400">(Last 12 Months)</span></span>
                <span class="db-chart-badge">Line Chart</span>
            </div>
            <div style="position:relative;height:200px">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
    </div>

</section>
