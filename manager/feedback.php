<?php
require_once 'auth_check.php';
require_once '../config/database.php';

// Fetch feedback/reviews
$feedback = [];
$error_msg = null;

$sql = "SELECT * FROM reviews ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedback[] = $row;
    }
} else {
    $error_msg = "Could not load feedback at this time. (" . $conn->error . ")";
}

include 'header.php';
?>

<section class="admin-section active">
    <div class="section-header">
        <h2>Customer Feedback</h2>
    </div>

    <?php if ($error_msg): ?>
        <div class="alert alert-warning" style="margin: 20px; padding: 15px; border-radius: 5px; color: #856404; background-color: #fff3cd; border-color: #ffeeba;">
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <div class="feedback-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; padding: 20px;">
        <?php if (count($feedback) > 0): ?>
            <?php foreach ($feedback as $review): ?>
                <div class="feedback-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: #2c3e50; font-size: 1.1rem;">
                            <?= htmlspecialchars($review['user_name'] ?? $review['customer_name'] ?? 'Anonymous') ?>
                        </h4>
                        <div style="color: #f1c40f;">
                            <?php 
                                $rating = isset($review['rating']) ? (int)$review['rating'] : 5;
                                for ($i = 0; $i < 5; $i++) {
                                    echo $i < $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                            ?>
                        </div>
                    </div>
                    <p style="color: #7f8c8d; font-style: italic; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;">
                        "<?= htmlspecialchars($review['review_text'] ?? $review['comment'] ?? 'No comment provided.') ?>"
                    </p>
                    <div style="font-size: 0.85rem; color: #bdc3c7;">
                        <i class="fas fa-clock"></i> <?= isset($review['created_at']) ? date('M d, Y', strtotime($review['created_at'])) : 'Unknown Date' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (!$error_msg): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #7f8c8d;">
                <i class="fas fa-comment-slash fa-3x" style="color: #bdc3c7; margin-bottom: 15px;"></i>
                <h3>No Customer Feedback Yet</h3>
                <p>Reviews left by customers will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'footer.php'; ?>
