@extends('layouts.app')

@section('content')
<main class="privacy-page">
    <article class="privacy-card">
        <header class="privacy-header">
            <a class="privacy-brand" href="{{ route('login') }}">
                <img src="{{ asset('images/bssa-logo.png') }}" alt="" width="56" height="56">
                <span>Batang Surigaonon Scholar's App</span>
            </a>
            <h1>Privacy Policy</h1>
            <p class="privacy-updated">Last updated 6 November 2026</p>
        </header>

        <p>This notice describes how the Batang Surigaonon Scholar's App handles information in the features that exist in this application. It is not a sign-in page, and you can read it without an account.</p>

        <aside class="privacy-note">
            <p><strong>Before this page is used as a formal legal policy, an administrator should confirm:</strong> the legal name of the organization that operates the scholarship; any retention rule required by the scholarship program that is not built into the application; that the live site is sending mail through the Gmail API; and whether any older document or attendance files are still stored in Google Drive.</p>
        </aside>

        <section>
            <h2>Information collected</h2>
            <p>Account registration collects the information entered on the registration form. For a scholar, that includes full name, scholar ID, email address, password, scholarship club, school or university, course, year level, and optional contact number and street address. City and province are stored from the selected scholarship club. For a scholarship-club staff registration, the form collects full name, email address, password, contact number, scholarship club name, club location, and an optional street address.</p>
            <p>After an account exists, the profile can also store a date of birth, guardian name, guardian relationship, guardian cellphone number, profile photo, academic year, semester, and notification preferences. Passwords are stored in hashed form. The application does not offer sign-in with a Google account.</p>
            <p>Scholarship documents uploaded by a scholar are stored with the file, original file name, academic year, upload time, review status, review notes, and the reviewer. Event participation can include a registration, check-in and check-out times, service hours, attendance status, remarks, and an attendance photo. The application also keeps in-app notifications and a short activity note, such as when an account or password change occurs.</p>
            <p>Login, registration, password changes, and account deletion use the email address and the request's IP address only to apply a short-lived rate limit. Those limits are not a permanent access log.</p>
        </section>

        <section>
            <h2>How information is used</h2>
            <p>The application uses this information to create and sign in accounts, place a scholar or staff member in a scholarship club, and operate the scholarship records in the system. That includes profile details, document submission and review, event registration, attendance, and service hours.</p>
            <p>New scholar and staff accounts start as pending until an authorized reviewer approves or rejects them. Approved accounts can use the dashboard for their role. A rejected account is signed out and cannot continue to use the system. A scholar can sign in while approval is still pending, with access limited until approval.</p>
        </section>

        <section>
            <h2>Who can see account records</h2>
            <p>The account holder can see and update their own profile and, for a scholar, their documents, events, and attendance. Administrators and scholar staff who manage that account's scholarship program or club can review the registration, documents, attendance, and related records needed to run the scholarship. The designated permanent administrator account cannot be deleted from the profile page.</p>
        </section>

        <section>
            <h2>Email and the Gmail API</h2>
            <p>The application sends messages about registration, account approval or rejection, scholarship activity, and password reset. Those messages are sent from <a href="mailto:batangsurigaononscholarsapp@gmail.com">batangsurigaononscholarsapp@gmail.com</a>. The project is built to send them through the Gmail API with a Google OAuth 2.0 client that belongs to the application. That connection lets the application send mail. It does not let a visitor sign in with Google, and it does not give the application access to a user's Gmail mailbox.</p>
            <p>When a message is sent through the Gmail API, Google receives the recipient address and the contents of that message, including a password-reset link when one is requested. The application can also be configured to send through another mail provider. An administrator should confirm which provider the live site is using.</p>
        </section>

        <section>
            <h2>Storage and security</h2>
            <p>Account records are stored in the application's MySQL database. New document uploads, attendance photos, and profile photos are saved on the application's file storage. Document and attendance records also have fields that can hold a Google Drive file identifier and link. The current upload process does not send new files to Google Drive. An administrator should confirm whether any older files still use those Google Drive fields.</p>
            <p>The deployed website is served over HTTPS. Passwords are hashed before they are stored. Session and security cookies are used to keep a signed-in visitor signed in and to protect forms. The application does not publish a separate security certification, encryption-at-rest guarantee, or fixed backup schedule.</p>
        </section>

        <section>
            <h2>Sharing</h2>
            <p>The application does not sell account information. Information is available to the administrators and scholar staff who manage the relevant scholarship records, as described above. Email delivery shares the recipient address and message with the configured email provider. Hosting and database operations are provided through the service that runs this website.</p>
        </section>

        <section>
            <h2>Retention and deletion</h2>
            <p>The application does not apply an automatic expiration date to accounts. Profile, document, and activity records remain until the account is deleted. A scholar can delete their own account from the profile page by entering their password. Deletion removes the account, stored documents, attendance files, profile photo, event registrations, notifications, and the password-reset record for that email address. The permanent administrator account cannot be deleted this way.</p>
            <p>A rejected scholar record can be removed when the same email address or scholar ID is used to register again, so those identifiers can be reused. This notice does not promise a recovery period after deletion. An administrator should confirm any scholarship-program rule that requires records to be kept for a particular time.</p>
        </section>

        <section>
            <h2>Privacy requests</h2>
            <p>A signed-in scholar can update profile details and delete their own account inside the application. For any other privacy request, write to <a href="mailto:batangsurigaononscholarsapp@gmail.com">batangsurigaononscholarsapp@gmail.com</a>. The application does not contain a separate privacy-request desk, and this notice does not promise a response time.</p>
        </section>

        <section>
            <h2>Changes to this notice</h2>
            <p>If the way the application handles information changes, this page will be updated and the date above will change. The page at this address is the current notice.</p>
        </section>

        <footer class="privacy-footer">
            <a class="privacy-back" href="{{ route('login') }}">Return to the website</a>
        </footer>
    </article>
</main>
@endsection

@push('styles')
<style>
    .privacy-page {
        min-height: 100vh;
        min-height: 100dvh;
        padding: 32px 16px 48px;
        background: linear-gradient(180deg, #e7f7ef, #f6f8fb 280px);
    }
    .privacy-card {
        width: min(800px, 100%);
        margin: 0 auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 6px 18px rgba(24, 39, 75, 0.06);
        padding: 32px 28px 28px;
        color: #1f2937;
        line-height: 1.6;
    }
    .privacy-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #1f2937;
        font-weight: 800;
        text-decoration: none;
    }
    .privacy-brand img {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
    }
    .privacy-header h1 {
        margin: 22px 0 4px;
        font-size: 32px;
        line-height: 1.2;
        color: #14532d;
    }
    .privacy-updated,
    .privacy-card p,
    .privacy-card li {
        font-size: 16px;
    }
    .privacy-updated {
        margin: 0 0 18px;
        color: #6b7280;
    }
    .privacy-note {
        margin: 20px 0;
        padding: 14px 16px;
        border-radius: 12px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
    }
    .privacy-note p {
        margin: 0;
    }
    .privacy-card h2 {
        margin: 28px 0 8px;
        font-size: 20px;
        line-height: 1.3;
        color: #166534;
    }
    .privacy-card a {
        color: #1e6e1e;
        font-weight: 700;
    }
    .privacy-card a:hover {
        text-decoration: underline;
    }
    .privacy-footer {
        margin-top: 32px;
        padding-top: 18px;
        border-top: 1px solid #e5e7eb;
    }
    .privacy-back {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 10px 16px;
        border-radius: 10px;
        background: #2fa76a;
        color: #fff !important;
        text-decoration: none;
        font-weight: 700;
    }
    .privacy-back:hover {
        text-decoration: none;
        background: #278a57;
    }
    @media (max-width: 640px) {
        .privacy-page {
            padding: 16px 12px 32px;
        }
        .privacy-card {
            padding: 22px 16px 18px;
            border-radius: 14px;
        }
        .privacy-header h1 {
            font-size: 28px;
        }
        .privacy-brand span {
            font-size: 15px;
        }
    }
</style>
@endpush
