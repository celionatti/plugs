<?php /* @compiled:51bed9214e76826b2b807821b7f23ecf */ ?>
<?php $__extends = 'layouts.admin'; ?>

<?php $__currentSection = 'title'; ob_start(); ?>
<?php echo e($title); ?>
<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?><?php $__currentSection = 'content'; ob_start(); ?>
<div class="container py-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="display-5 fw-bold text-primary mb-0">Manage Posts</h1>
                    <p class="text-muted">Create, edit, and delete your blog posts.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/admin/posts/ai-suggestions" class="btn btn-outline-primary">
                        <i class="bi bi-stars me-1"></i> AI Suggestions
                    </a>
                    <a href="/admin/posts/create" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create New Post
                    </a>
                </div>
            </div>

            <?php if (session()->has('success')): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo e(session()->get('success')); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?><div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(isset($posts) && is_iterable($posts)): $loop = new \Plugs\View\Loop($posts, $loop ?? null, (($loop->depth ?? 0) + 1)); foreach ($posts as $post): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark"><?php echo e($post->title); ?></div>
                                            <small class="text-muted"><?php echo e($post->slug); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info px-2"><?php echo e($post->category->name); ?></span>
                                        </td>
                                        <td>
                                            <span
                                                class="badge <?php echo e($post->status === 'published' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'); ?> px-2">
                                                <?php echo e(ucfirst($post->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo e(date('M d, Y', strtotime($post->created_at))); ?></td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group">
                                                <a href="/admin/posts/audit/<?php echo e($post->slug); ?>"
                                                    class="btn btn-sm btn-outline-primary" title="AI Content Audit">
                                                    <i class="bi bi-shield-check"></i>
                                                </a>
                                                <a href="/admin/posts/edit/<?php echo e($post->slug); ?>"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <a href="/admin/posts/delete/<?php echo e($post->slug); ?>"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Are you sure you want to delete this post?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php $loop->tick(); if (isset($this) && method_exists($this, "isAutoFlushEnabled") && $this->isAutoFlushEnabled() && $loop->shouldFlush($this->getAutoFlushFrequency())) flush(); endforeach; $loop = $loop->parent ?? null; endif; ?></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 d-flex justify-content-center">
                    <?php echo $posts->links(); ?>
                </div>
            </div>
        </div>
<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>