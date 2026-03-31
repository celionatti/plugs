<?php
/**
 * Premium Notification Component
 * Framework-native, zero-dependency, Vanilla JS dismissal.
 */
$id = 'plug_notify_' . uniqid();
$duration = $duration ?? 5000;
$type = $type ?? 'success';
$title = $title ?? ucfirst($type);
?>

<div id="{{ $id }}" class="plugs-premium-notification" style="display: none;">
    <div class="notify-glass glass shadow-2xl">
        <div class="notify-content">
            <div class="notify-icon-wrapper {{ $type }}">
                @if($type === 'success')
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                @elseif($type === 'error')
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                @endif
            </div>

            <div class="notify-text">
                <h4 class="{{ $type }}-title">{{ $title }}</h4>
                <p>{{ $message }}</p>
            </div>

            <button type="button" class="notify-close" onclick="dismissNotify('{{ $id }}')">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="notify-progress-bg">
            <div id="{{ $id }}_progress" class="notify-progress-bar {{ $type }}"></div>
        </div>
    </div>
</div>

<style>
    .plugs-premium-notification {
        position: fixed;
        top: 2rem;
        right: 2rem;
        z-index: 9999;
        min-width: 320px;
        font-family: inherit;
        animation: notify-slide-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .notify-glass {
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 1rem;
        overflow: hidden;
        position: relative;
    }

    .notify-content {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.25rem;
    }

    .notify-icon-wrapper {
        flex-shrink: 0;
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
    }

    .notify-icon-wrapper.success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .notify-icon-wrapper.error { background: rgba(244, 63, 94, 0.15); color: #f43f5e; }
    .notify-icon-wrapper.info { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }

    .notify-text { flex: 1; min-width: 0; }
    .notify-text h4 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #fff; }
    .notify-text p { margin: 0.25rem 0 0; font-size: 0.85rem; color: rgba(255, 255, 255, 0.7); line-height: 1.4; }

    .success-title { color: #34d399 !important; }
    .error-title { color: #fb7185 !important; }
    .info-title { color: #60a5fa !important; }

    .notify-close {
        flex-shrink: 0;
        background: none;
        border: none;
        padding: 0;
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        transition: color 0.2s;
    }
    .notify-close:hover { color: #fff; }

    .notify-progress-bg { position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: rgba(255, 255, 255, 0.05); }
    .notify-progress-bar { height: 100%; width: 100%; transition: width linear; }
    .notify-progress-bar.success { background: #10b981; }
    .notify-progress-bar.error { background: #f43f5e; }
    .notify-progress-bar.info { background: #3b82f6; }

    @keyframes notify-slide-in {
        from { opacity: 0; transform: translateX(2rem) scale(0.95); }
        to { opacity: 1; transform: translateX(0) scale(1); }
    }

    @keyframes notify-slide-out {
        from { opacity: 1; transform: translateX(0) scale(1); }
        to { opacity: 0; transform: translateX(2rem) scale(0.95); }
    }

    .notify-exit { animation: notify-slide-out 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards !important; }
</style>

<script>
    (function() {
        const id = '{{ $id }}';
        const el = document.getElementById(id);
        const progressEl = document.getElementById(id + '_progress');
        const duration = {{ $duration }};
        
        // Show the notification with animation
        el.style.display = 'block';

        // Start progress bar depletion
        let startTime = Date.now();
        let interval = setInterval(() => {
            let elapsed = Date.now() - startTime;
            let remaining = Math.max(0, 100 - (elapsed / duration * 100));
            progressEl.style.width = remaining + '%';

            if (elapsed >= duration) {
                clearInterval(interval);
                dismissNotify(id);
            }
        }, 16);

        // Global dismiss function if not exists
        window.dismissNotify = window.dismissNotify || function(notifyId) {
            const target = document.getElementById(notifyId);
            if (!target) return;
            
            target.classList.add('notify-exit');
            setTimeout(() => {
                target.remove();
            }, 400);
        };
    })();
</script>
