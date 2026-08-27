<div class="card notifications-overview-sidebar-card">
    <div class="card-header">NOTIFICATION OVERVIEW</div>
    <div class="progress-wrap compact">
        <div class="progress-circle">
            <svg viewBox="0 0 36 36" aria-hidden="true">
                <path class="bg" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
                <path class="meter blue" stroke-dasharray="100,100" d="M18 2.0845a15.9155 15.9155 0 1 0 0 31.831 15.9155 15.9155 0 1 0 0-31.831"/>
            </svg>
            <div class="progress-text">{{ $notifStats['total'] }}<br><small>Total</small></div>
        </div>
        <div class="hours-list">
            <div><span class="dot unread-dot"></span> Unread <strong>{{ $notifStats['unread'] }}</strong></div>
            <div><span class="dot completed"></span> Read <strong>{{ $notifStats['read'] }}</strong></div>
            <div><span class="dot rejected-dot"></span> Important <strong>{{ $notifStats['important'] }}</strong></div>
        </div>
    </div>
</div>
