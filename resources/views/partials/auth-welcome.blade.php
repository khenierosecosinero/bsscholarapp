@once
<style>
    .auth-page .welcome-section {
        max-width: 320px;
        width: 100%;
    }

    .auth-page .welcome-logo {
        width: 104px;
        height: 104px;
        max-width: 104px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
        flex-shrink: 0;
        margin: 0 auto 4px;
    }

    .auth-page .welcome-actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        width: 100%;
        margin-top: 4px;
    }

    .auth-page .welcome-action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 48px;
        padding: 14px 18px;
        border-radius: 14px;
        font-weight: 700;
        font-size: 15px;
        line-height: 1.2;
        text-align: center;
        text-decoration: none;
        box-sizing: border-box;
        white-space: nowrap;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s, border-color 0.2s, box-shadow 0.2s;
    }

    .auth-page .welcome-action-btn-filled {
        background: #1E6E1E;
        color: #fff;
        border: 2px solid #1E6E1E;
    }

    .auth-page .welcome-action-btn-filled:hover {
        background: #185a18;
        border-color: #185a18;
    }

    .auth-page .welcome-action-btn-outline {
        background: #fff;
        color: #1E6E1E;
        border: 2px solid #1E6E1E;
    }

    .auth-page .welcome-action-btn-outline:hover {
        background: #f3faf3;
        color: #185a18;
        border-color: #185a18;
    }

    .auth-page .welcome-action-btn-outline:focus,
    .auth-page .welcome-action-btn-outline:active {
        background: #fff;
        color: #1E6E1E;
        border-color: #1E6E1E;
        box-shadow: 0 4px 16px #1f2937;
        outline: none;
    }

    .auth-page .welcome-action-btn:active {
        transform: scale(0.98);
    }

    @media (max-width: 480px) {
        .auth-page .welcome-section {
            max-width: 100%;
        }

        .auth-page .welcome-logo {
            width: 76px;
            height: 76px;
            max-width: 76px;
        }

        .auth-page .welcome-action-btn {
            font-size: 14px;
            padding: 12px 14px;
        }
    }
</style>
@endonce

<div class="right-panel">
    <div class="welcome-section">
        <img class="welcome-logo" src="{{ asset('images/bssa-logo.png') }}" alt="Batang Surigaonon Scholar's App logo">
        <h4>Hello, Welcome to</h4>
        <h2>BATANG<br>SURIGAONON<br>SCHOLAR'S APP!</h2>
        <p>Manage your service hours, join events, submit documents, and stay updated.</p>
        <div class="welcome-actions">
            <a href="{{ $ctaRoute }}" class="welcome-action-btn {{ ($ctaVariant ?? 'filled') === 'outline' ? 'welcome-action-btn-outline' : 'welcome-action-btn-filled' }}">{{ $ctaLabel }}</a>
            @if(!empty($staffRegisterRoute))
                <a href="{{ $staffRegisterRoute }}" class="welcome-action-btn welcome-action-btn-outline">Scholarship Club Registration</a>
            @endif
        </div>
    </div>
</div>
