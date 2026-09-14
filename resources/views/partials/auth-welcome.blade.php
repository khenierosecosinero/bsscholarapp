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
        transition: background 0.2s, transform 0.15s, border-color 0.2s;
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
        background: transparent;
        color: #fff;
        border: 2px solid #fff;
    }

    .auth-page .welcome-action-btn-outline:hover {
        background: rgba(255, 255, 255, 0.12);
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
            <a href="{{ $ctaRoute }}" class="welcome-action-btn welcome-action-btn-filled">{{ $ctaLabel }}</a>
            @if(!empty($staffRegisterRoute))
                <a href="{{ $staffRegisterRoute }}" class="welcome-action-btn welcome-action-btn-outline">City's Scholar Registration</a>
            @endif
        </div>
    </div>
</div>
