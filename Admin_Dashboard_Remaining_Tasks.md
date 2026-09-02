# Super Admin Dashboard — বাকি কাজের তালিকা

> এই ফাইলটি ধাপে ধাপে কাজ করার জন্য তৈরি। প্রতিটি কাজ শেষ হলে `[ ]` কে `[x]` করে দাও।

---

## ধাপ ১ — Category Management (Dynamic CRUD)
> এখন categories শুধু config ফাইলে hardcoded। Database-driven করতে হবে।

- [x] `categories` টেবিল তৈরি করা (database migration) — `menu_categories` table auto-created on first load
- [x] Admin dashboard-এ **Category Management** section যোগ করা
- [x] Category Add / Edit / Delete করার সুবিধা
- [x] Category-এর Active / Inactive status toggle
- [x] Category Sort Order (↑↓ button দিয়ে)
- [x] Menu Management-এ category dropdown dynamic করা (config থেকে DB-তে নেওয়া)
- [x] Public menu page (`pages/menu.php`) dynamic categories থেকে load করা
- [x] `config/categories.php` backward-compatible fallback হিসেবে রাখা

---

## ধাপ ২ — Menu Item উন্নতি
> বর্তমানে single image এবং stock field নেই।

- [x] **Multiple Images** সাপোর্ট যোগ করা (gallery-style, add/remove — max 5 extra photos)
- [x] **Stock Availability** field যোগ করা (in_stock toggle + optional quantity)
- [x] Menu item-এ **Stock status** দেখানো admin table-এ (In Stock / Out of Stock badge)
- [x] Public menu page-এ **Out of Stock** overlay ও disabled Order button
- [ ] Dashboard-এর **Low Stock Items** widget menu_items stock দেখাবে (inventory-তে আছে, menu stock আলাদা widget বাকি)

---

## ধাপ ৩ — Dashboard উন্নতি
> কিছু গুরুত্বপূর্ণ widget এখনো নেই।

- [x] **Revenue Chart** যোগ করা (Chart.js দিয়ে line/bar chart)
  - Daily revenue (last 7 days)
  - Monthly revenue (last 12 months)
- [x] **Low Stock Alert** widget Dashboard-এ দেখানো
- [x] **Staff Attendance Summary** widget (আজকে কতজন present/absent)
- [x] **Today's Sales vs Yesterday** comparison card

---

## ধাপ ৪ — Restaurant Information Management
> Settings section আংশিক আছে, কিছু বাকি।

- [x] **Logo Upload** — ছবি আপলোড করে save করা এবং site-এ দেখানো
- [x] **Cover Image Upload** — background/banner ছবি (homepage hero-তে দেখানো হচ্ছে)
- [x] **Google Map Embed Preview** — settings-এ map URL দিলে preview দেখানো এবং contact page-এ embed
- [x] Settings save হলে frontend (public site) এ সাথে সাথে reflect হওয়া
  - `config/settings_helper.php` তৈরি করা হয়েছে (সব pages shared helper থেকে settings নেয়)
  - `index.php`, `pages/contact.php`, `pages/about.php`, `pages/menu.php` — সব dynamic
  - Logo, cover, name, address, phone, email, hours, social links — সব DB-থেকে

---

## ধাপ ৫ — Staff Management উন্নতি
> Role system সীমিত এবং performance tracking নেই।

- [ ] Staff Role-এ বিস্তারিত roles যোগ করা:
  - Chef, Waiter, Cashier, Receptionist, 
- [ ] প্রতিটি Role-এর জন্য আলাদা **badge/color** দেখানো
- [ ] **Performance Tracking** section:
  - Monthly order handled count (waiter/cashier)
  - Delivery completed count (delivery boy)
  - Rating/review
- [ ] Staff Profile page (detail view modal উন্নত করা)

---

## ধাপ ৬ — RBAC (Role-Based Access Control)
> এটি সবচেয়ে গুরুত্বপূর্ণ বাকি কাজ।

- [x] **`config/rbac.php`** তৈরি করা — permission helper (rbac_can, rbac_init, rbac_allowed_sections)
- [x] **`role_permissions` টেবিল** auto-create + default seed (DB migration)
- [x] প্রতিটি role কোন section দেখতে পাবে — default permissions সেট করা
- [x] Login করার পর role অনুযায়ী redirect:
  - admin → admin panel (full access)
  - manager → manager panel (আগের মতো)
  - chef / waiter / cashier  / staff → admin panel (restricted)
- [x] Admin panel sidebar dynamically filtered (role অনুযায়ী শুধু allowed sections দেখায়)
- [x] Direct URL hash access blocked (JS + PHP উভয়ে)
- [x] API endpoints-এ RBAC permission check (add/edit/delete menu, orders, settings)
- [x] Non-admin staff-এর জন্য role welcome banner (কোন sections accessible তা দেখায়)
- [x] Admin panel-এ **Role Permission Editor** (`manage-rbac.php`) — toggle করে কোন role কী দেখবে
- [x] **Quick Presets** — একক role-এর সব permission এক ক্লিকে Default/None করা
- [x] **Group toggle** — Operations/Finance ইত্যাদি group-এর সব permission একসাথে on/off
- [ ] Role Permission Editor-এ **Real-time preview** (change করলে সাথে সাথে sidebar preview দেখানো)

---

## ধাপ ৭ — Notification System উন্নতি
> এখন শুধু in-app notification আছে।

- [ ] **Email Notification** integration (PHPMailer দিয়ে):
  - নতুন order হলে admin-কে email
  - Reservation confirm/cancel হলে customer-কে email
  - Order status পরিবর্তন হলে customer-কে email
- [ ] **SMS Notification** (বাংলাদেশি gateway — যেমন SSL Wireless বা Twilio):
  - Order confirmation SMS
  - Reservation reminder SMS
- [ ] Notification Settings পেজ (কোন event-এ কোন notification চালু/বন্ধ)

---

## ধাপ ৮ — Security উন্নতি
> Login security এবং audit trail বাকি।

- [ ] **Login History** পেজ — কে কখন login করেছে (IP, device, সময়)
- [ ] **Failed Login Alert** — বারবার ব্যর্থ login হলে notification
- [ ] **Session Timeout** — নির্দিষ্ট সময় পর auto logout
- [ ] **2FA (Two Factor Authentication)** — optional, email OTP দিয়ে
- [ ] **Backup & Restore** UI — database backup download করার সুবিধা

---

## ধাপ ৯ — Reports উন্নতি
> কিছু report এখনো নেই এবং export নেই।

- [ ] **Profit & Loss Report** — Revenue minus Expenses
- [ ] **Tax / VAT Report** — মাসিক tax collected
- [ ] **Expense Report** — category-wise expense breakdown
- [ ] **Inventory Report** — low stock, expiring items
- [ ] **Staff Salary Report** — monthly salary summary
- [ ] **Export feature** সব report-এ:
  - PDF download
  - Excel / CSV download
- [ ] **Print** button প্রতিটি report-এ

---

## ধাপ ১০ — Frontend (Public Site) সংযোগ
> Admin থেকে পরিবর্তন করলে public site-এ reflect হওয়া।

- [ ] Homepage **Banner/Slider** admin থেকে manage করা
- [ ] **Featured Foods** section dynamic করা (admin থেকে select করা)
- [ ] **Testimonials** section admin থেকে add/edit/delete
- [ ] **FAQ** section admin থেকে manage করা
- [ ] **Blog/Events** public page-এ দেখানো (admin থেকে publish)
- [ ] **Contact Information** (phone, email, address, map) admin settings থেকে নেওয়া

---

## ধাপ ১১ — অতিরিক্ত ছোট উন্নতিসমূহ

- [ ] Order-এ **Payment Method** filter যোগ করা
- [ ] Reservation-এ **Table Availability** calendar view
- [ ] Customer-কে **Loyalty Points** automatically যোগ করা (order complete হলে)
- [ ] Coupon-এ **Customer-specific coupon** (নির্দিষ্ট customer-এর জন্য)
- [ ] **Dark/Light mode** toggle (optional)
- [ ] Mobile responsive উন্নতি (admin panel)
- [ ] **Bulk actions** — multiple orders/reservations একসাথে update করা
- [ ] **Search** — সব section-এ global search

---

## অগ্রাধিকার (Priority) অনুযায়ী সাজানো

| Priority | কাজ | Status |
|----------|-----|--------|
| ✅ Done | ধাপ ১ — Category CRUD | Complete |
| ✅ Done | ধাপ ২ — Menu Multiple Images + Stock | Complete |
| ✅ Done | ধাপ ৩ — Dashboard Charts | Complete |
| ✅ Done | ধাপ ৪ — Restaurant Info / Settings | Complete |
| ✅ Done | ধাপ ৬ — RBAC | Complete |
| 🔴 High | ধাপ ৯ — Reports Export (PDF/Excel) | বাকি |
| 🟡 Medium | ধাপ ৭ — Email/SMS Notification | বাকি |
| 🟡 Medium | ধাপ ১০ — Frontend সংযোগ (Banner/Testimonials/FAQ) | বাকি |
| 🟢 Normal | ধাপ ৮ — Security (2FA, Backup) | বাকি |
| 🔵 Low | ধাপ ১১ — ছোট উন্নতি (Bulk actions, Dark mode) | বাকি |

---

> **শেষ আপডেট:** বর্তমান কোড বিশ্লেষণের ভিত্তিতে তৈরি।  
> প্রতিটি ধাপ শেষে এই ফাইল আপডেট করো।
