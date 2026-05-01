<?php

declare(strict_types=1);

require __DIR__ . '/../includes/page.php';

$currentUser = requireAuthenticatedPage();
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_task') {
        $title = trim((string) ($_POST['title'] ?? ''));

        if ($title === '' || strlen($title) > 120) {
            $errorMessage = 'Task title is required and must be 120 characters or fewer.';
        } elseif (createTask($currentUser, $title) === null) {
            $errorMessage = 'Unable to create task.';
        } else {
            $successMessage = 'Task created.';
        }
    } elseif ($action === 'toggle_task') {
        $taskId = trim((string) ($_POST['task_id'] ?? ''));
        $completed = ($_POST['completed'] ?? '') === '1';

        if ($taskId === '' || updateTask($currentUser, $taskId, ['completed' => $completed]) === null) {
            $errorMessage = 'Task not found.';
        } else {
            $successMessage = 'Task updated.';
        }
    } elseif ($action === 'delete_task') {
        $taskId = trim((string) ($_POST['task_id'] ?? ''));

        if ($taskId === '' || !deleteTask($currentUser, $taskId)) {
            $errorMessage = 'Task not found.';
        } else {
            $successMessage = 'Task deleted.';
        }
    } else {
        $errorMessage = 'Unknown task action.';
    }
}

$tasks = tasksForUser($currentUser);
$openTaskCount = count(array_filter($tasks, static fn (array $task): bool => ($task['completed'] ?? false) === false));

renderHtmlHead('Activity');
?>
<body class="app-body">
    <?php renderBackgroundElements(); ?>

    <div class="app-shell">
        <?php renderAuthenticatedNav('activity', $currentUser); ?>

        <main id="activity-page" class="glass-panel active wide-panel">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">Protected page</p>
                    <h1>Activity</h1>
                    <p>Create and manage tasks backed by the task API.</p>
                </div>
                <div class="status-pill" id="activity-open-count"><?= h((string) $openTaskCount) ?> open</div>
            </div>

            <section class="content-grid two-column">
                <article class="action-card">
                    <h2>Add task</h2>
                    <form id="task-form" method="post" action="/activity/" class="stacked-form">
                        <input type="hidden" name="action" value="create_task">
                        <div class="input-group">
                            <label for="task-title">Task title</label>
                            <input type="text" id="task-title" name="title" placeholder="Review API executor output" required>
                        </div>
                        <?php if ($errorMessage !== ''): ?>
                            <div class="error-message"><?= h($errorMessage) ?></div>
                        <?php endif; ?>
                        <?php if ($successMessage !== ''): ?>
                            <div class="success-message"><?= h($successMessage) ?></div>
                        <?php endif; ?>
                        <button type="submit" class="btn primary-btn">Create Task</button>
                    </form>
                </article>

                <article class="action-card">
                    <h2>API coverage targets</h2>
                    <p>The executor can verify authenticated reads, validation failures, creation, updates, deletion, and method rejection.</p>
                    <code>401 Authentication required</code>
                    <code>201 Created</code>
                    <code>204 No Content</code>
                    <code>405 Method not allowed</code>
                </article>
            </section>

            <section class="action-card task-list-card">
                <h2>Task list</h2>
                <?php if ($tasks === []): ?>
                    <p id="empty-task-list" class="empty-state">No tasks yet.</p>
                <?php else: ?>
                    <div class="task-list" id="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $taskId = (string) ($task['id'] ?? '');
                            $completed = !empty($task['completed']);
                            ?>
                            <article class="task-row <?= $completed ? 'completed' : '' ?>">
                                <div>
                                    <strong><?= h((string) ($task['title'] ?? 'Untitled task')) ?></strong>
                                    <span><?= $completed ? 'Completed' : 'Open' ?></span>
                                </div>
                                <div class="task-actions">
                                    <form method="post" action="/activity/">
                                        <input type="hidden" name="action" value="toggle_task">
                                        <input type="hidden" name="task_id" value="<?= h($taskId) ?>">
                                        <input type="hidden" name="completed" value="<?= $completed ? '0' : '1' ?>">
                                        <button type="submit" class="btn compact-btn"><?= $completed ? 'Reopen' : 'Complete' ?></button>
                                    </form>
                                    <form method="post" action="/activity/">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?= h($taskId) ?>">
                                        <button type="submit" class="btn compact-btn danger-btn">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
