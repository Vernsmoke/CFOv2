<!doctype html>
<html lang="en" class="cfo-design" data-cfo-surface="student">
<head>
<meta name="cfo-backend" content="{{ json_encode($cfoBackend) }}">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00543f">
  <title>Student Panel | Christian Formation Office</title>
  <!--
    SELF-CONTAINED STUDENT PANEL — editable source, not a script loader.
    7.1 HTML: shared structure, dialogs and named page/field templates in the body.
    7.2 CSS: all original styling, responsive rules and embedded images below.
    7.3 JAVASCRIPT: all interactions in the single script immediately before /body.
    Open this file directly to preview. Use #login, #dashboard,
    #recollection-registration, #schedule-change-request, #recollection-history,
    #certificate or #my-profile to open a screen. No external UI files are needed.
    File preview does not authenticate or persist school records; Laravel mode
    uses the existing backend when this file is served at /student-panel.html.
  -->
  <!-- ==================== 7.2 CSS / STYLING ==================== -->
  <style>

/* 7.2.1 — shared */
:root {
  font-family: "Segoe UI", Arial, sans-serif;
  color: #233544;
  font-size: 16px;
  font-synthesis: none;
  --green: #075a43;
  --dark: #073d33;
  --muted: #7c8794;
  --border: #d7dde5;
  --field: #fafafb;
}
* {
  box-sizing: border-box;
}
body {
  margin: 0;
  min-height: 100vh;
  background:
    radial-gradient(ellipse at 82% 5%, #1c6552 0, transparent 45%),
    radial-gradient(ellipse at 15% 60%, #075643 0, transparent 50%), #003c2f;
}
button,
input,
select {
  font: inherit;
}
button,
a,
input,
select {
  -webkit-tap-highlight-color: transparent;
}
button,
a {
  touch-action: manipulation;
}
button {
  cursor: pointer;
}
button:focus-visible,
a:focus-visible {
  outline: 3px solid #54b9a0;
  outline-offset: 4px;
}
input:focus,
select:focus {
  outline: 0;
  border-color: #27846b;
  box-shadow: 0 0 0 3px #27846b18;
}
svg.icon {
  width: 22px;
  height: 22px;
  flex-shrink: 0;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
  vertical-align: middle;
}
h1,
h2,
p {
  margin: 0;
}
a {
  color: var(--green);
  text-decoration: none;
}
button {
  border: 0;
}
button:hover {
  filter: brightness(0.96);
}
.campus {
  pointer-events: none;
  position: fixed;
  bottom: 0;
  width: 25%;
  height: 42vw;
  max-height: 480px;
  background-size: 100vw auto;
  background-repeat: no-repeat;
  opacity: 0.55;
  z-index: -1;
  mask-image: linear-gradient(transparent, #000 30%);
}
.campus-left {
  left: 0;
  background-position: left bottom;
}
.campus-right {
  right: 0;
  width: 27%;
  background-position: right bottom;
}
.login-page {
  max-width: 860px;
  margin: auto;
  padding: 28px 20px 52px;
}
.login-heading {
  text-align: center;
  color: #fff;
  margin-bottom: 32px;
}
.login-heading h1 {
  font-size: clamp(2.1rem, 5.6vw, 4.7rem);
  line-height: 1.12;
  font-weight: 750;
  letter-spacing: -1.6px;
  white-space: nowrap;
  margin-left: -150px;
  margin-right: -150px;
}
.login-heading p {
  font-size: 1.25rem;
  margin-top: 24px;
}
.quick-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 22px;
  margin-bottom: 24px;
}
.quick-card {
  display: flex;
  gap: 21px;
  align-items: center;
  text-align: left;
  min-height: 112px;
  padding: 20px 25px;
  border-radius: 11px;
  border: 1px solid #ccdcd6;
  background: #fdfdfe;
  color: #033d32;
}
.quick-card strong {
  display: block;
  font-size: 1.4rem;
  margin-bottom: 5px;
}
.quick-card span {
  font-size: 1.06rem;
  line-height: 1.4;
}
.quick-card > .icon {
  width: 40px;
  height: 40px;
}
.quick-card > .chevron {
  margin-left: auto;
  width: 24px;
  height: 24px;
}
.quick-card.secondary {
  color: white;
  background: #00392e88;
  border: 1px solid #73988d;
}
.quick-card.secondary > .icon:first-child {
  border-radius: 50%;
  background: #fff2;
  padding: 12px;
  width: 65px;
  height: 65px;
}
.login-card {
  max-width: 650px;
  background: #fdfdff;
  border-radius: 18px;
  margin: auto;
  padding: 28px 35px 36px;
  box-shadow: 0 10px 24px #00261f28;
}
.login-title {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
  color: #052f29;
  font-size: 1.85rem;
}
.title-icon {
  border-radius: 50%;
  padding: 12px;
  background: #e5f1ed;
  color: #006047;
}
.title-icon .icon {
  width: 28px;
  height: 28px;
}
.login-description {
  text-align: center;
  color: #475c7c;
  margin: 5px 0 26px;
  font-size: 1.05rem;
}
.field {
  display: block;
  margin-bottom: 21px;
  min-width: 0;
}
.field > label {
  display: block;
  font-weight: 600;
  margin-bottom: 9px;
}
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-wrap > .icon {
  position: absolute;
  left: 17px;
  pointer-events: none;
  color: #344958;
}
.input-wrap input,
.input-wrap select {
  width: 100%;
  height: 51px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--field);
  padding: 0 16px 0 52px;
  color: #283c4c;
  min-width: 0;
}
.input-wrap input::placeholder {
  color: #7b8794;
  opacity: 1;
}
.input-wrap select {
  appearance: none;
  padding-right: 40px;
}
.input-wrap .select-chevron {
  left: auto;
  right: 15px;
  width: 18px;
}
.field small {
  display: block;
  color: #536682;
  font-size: 0.9rem;
  line-height: 1.45;
  margin-top: 9px;
}
.password-input input {
  padding-right: 52px;
}
.reveal {
  position: absolute;
  right: 8px;
  padding: 8px;
  background: none;
  color: #2d4352;
  display: grid;
  place-items: center;
}
.remember {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 4px 0 23px;
  font-size: 0.95rem;
  color: #07342f;
}
.remember input {
  width: 19px;
  height: 19px;
  accent-color: var(--green);
  margin: 0;
}
.primary,
.outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 13px;
  min-height: 55px;
  padding: 12px 28px;
  border-radius: 8px;
  font-weight: 650;
  font-size: 1.06rem;
}
.primary {
  background: linear-gradient(110deg, #086049, #04543e);
  color: white;
  border: 1px solid #075b44;
}
.outline {
  border: 1px solid #bfc9d4;
  background: #f7f8fa;
  color: #253847;
}
.full {
  width: 100%;
}
.divider {
  display: flex;
  align-items: center;
  gap: 20px;
  color: #526482;
  margin: 23px 0;
}
.divider:before,
.divider:after {
  content: "";
  height: 1px;
  background: var(--border);
  flex: 1;
}
.google {
  background: transparent;
  border: 1px solid var(--border);
  color: #172e40;
}
.google-mark {
  font-family: Arial, sans-serif;
  font-weight: 800;
  font-size: 26px;
  background: conic-gradient(
    #4285f4 0deg 100deg,
    #34a853 100deg 180deg,
    #fbbc05 180deg 240deg,
    #ea4335 240deg 290deg,
    #4285f4 290deg
  );
  background-clip: text;
  color: transparent;
}
.registration-mode {
  background:
    radial-gradient(ellipse at top left, #96b4bb, transparent 45%),
    linear-gradient(110deg, #b2c8cb, #edf4f6 80%);
}
.registration-mode > .campus {
  display: none;
}
.registration-shell {
  display: grid;
  grid-template-columns: 30% 70%;
  max-width: 1240px;
  margin: 48px auto;
  min-height: 1035px;
  border-radius: 15px;
  overflow: hidden;
  box-shadow: 0 20px 65px #173c4620;
}
.brand-panel {
  position: relative;
  overflow: hidden;
  background: #185b4a;
  isolation: isolate;
  min-height: 100%;
}
.brand-panel img {
  position: absolute;
  height: 100%;
  width: 360.7%;
  max-width: none;
  left: -13.75%;
  top: 0;
  object-fit: fill;
}
.brand-panel .brand-crop {
  position: absolute;
  inset: 0;
  overflow: hidden;
}
.brand-panel .brand-crop img {
  height: 109.7%;
  top: -4.75%;
}
.registration-main {
  background: #fff;
  padding: 38px 42px 34px;
  min-width: 0;
  border-radius: 14px;
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  font-size: 0.9rem;
}
.back-link {
  display: inline-flex;
  gap: 10px;
  align-items: center;
  font-weight: 600;
  background: none;
  padding: 0;
  color: var(--green);
  white-space: nowrap;
}
.topbar p {
  color: var(--muted);
  text-align: right;
}
.topbar a {
  font-weight: 600;
}
.register-heading {
  text-align: center;
  margin: 40px 0 34px;
}
.register-heading h1 {
  font:
    700 2.8rem/1.1 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #1c3740;
}
.register-heading p {
  margin-top: 9px;
  color: var(--muted);
  font-size: 1.12rem;
}
.steps {
  display: flex;
  list-style: none;
  padding: 0;
  margin: 0 0 37px;
  position: relative;
}
.steps li {
  flex: 1;
  text-align: center;
  position: relative;
  color: #7d8895;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
}
.steps li:before {
  content: "";
  position: absolute;
  top: 21px;
  height: 2px;
  left: -50%;
  width: 100%;
  background: #d1d6dd;
}
.steps li:first-child:before {
  display: none;
}
.steps li.done:before,
.steps li.active:before {
  background: var(--green);
}
.step-number {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 43px;
  height: 43px;
  border-radius: 50%;
  background: #ccd1d9;
  color: white;
  font-size: 1.45rem;
  margin: 0 auto 10px;
  z-index: 1;
}
.active .step-number,
.done .step-number {
  background: linear-gradient(110deg, #006041, #247d60);
}
.steps .active {
  color: var(--green);
}
.banner {
  display: flex;
  align-items: center;
  gap: 24px;
  background: #edf6f1;
  border-radius: 9px;
  padding: 20px 25px;
  margin-bottom: 28px;
  color: #176047;
  line-height: 1.4;
}
.banner > .icon {
  width: 40px;
  height: 40px;
}
.banner strong {
  display: block;
  font-size: 1.1rem;
  margin-bottom: 3px;
}
.banner p {
  font-size: 1rem;
}
.registration-main .field {
  margin-bottom: 25px;
}
.registration-main .input-wrap input,
.registration-main .input-wrap select {
  height: 52px;
}
.role-field {
  width: 49%;
}
.name-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 15px;
}
.name-row .input-wrap input {
  padding-left: 43px;
  padding-right: 8px;
  font-size: 0.9rem;
}
.name-row .input-wrap > .icon {
  left: 12px;
}
.form-actions {
  display: flex;
  justify-content: space-between;
  gap: 15px;
  margin-top: 34px;
}
.form-actions button {
  min-width: 180px;
}
.reminder {
  display: flex;
  gap: 23px;
  margin-top: 33px;
  padding: 21px 25px;
  border-radius: 9px;
  background: #f1f5f5;
  font-size: 0.92rem;
  line-height: 1.65;
}
.reminder > .icon {
  color: #478871;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  margin-top: 3px;
}
.reminder strong {
  color: #24614e;
  display: block;
  font-size: 1rem;
}
.reminder.blue {
  background: #eaf3ff;
  color: #3c5f8c;
  margin-top: 27px;
}
.reminder.blue strong,
.reminder.blue > .icon {
  color: #2468ab;
}
.requirements {
  background: #f1f8f5;
  border: 1px solid #d4e7de;
  border-radius: 9px;
  padding: 22px 27px;
  display: flex;
  gap: 22px;
  margin-top: 27px;
}
.requirements > .icon {
  color: var(--green);
  width: 36px;
  height: 36px;
}
.requirements h2 {
  font-size: 1.08rem;
  color: #176047;
  margin-bottom: 12px;
}
.requirements ul {
  padding: 0;
  margin: 0;
  list-style: none;
}
.requirements li {
  display: flex;
  gap: 11px;
  align-items: center;
  line-height: 1.65;
}
.rule-dot {
  width: 17px;
  height: 17px;
  display: inline-flex;
  border: 2px solid #a6b1bb;
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 11px;
}
.valid {
  color: #086046;
}
.valid .rule-dot {
  background: #176047;
  border-color: #176047;
  color: white;
}
.review-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 20px 0 7px;
}
.review-heading h2 {
  font-size: 1.13rem;
}
.edit-button {
  padding: 8px 15px;
  min-height: 40px;
  font-size: 0.94rem;
  gap: 10px;
}
.details {
  margin: 0;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 18px;
  background: #fafafb;
}
.detail-row {
  display: grid;
  grid-template-columns: 34% 1fr;
  gap: 20px;
  padding: 10px 0;
  border-bottom: 1px solid #e7ebf0;
  font-size: 0.95rem;
  line-height: 1.35;
}
.detail-row:last-child {
  border-bottom: 0;
}
.detail-row dt {
  font-weight: 600;
  position: relative;
}
.detail-row dt:after {
  content: ":";
  position: absolute;
  right: 0;
  color: #8d97a4;
  font-weight: 400;
}
.detail-row dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.error {
  color: #ac3232;
  font-size: 0.9rem;
  margin: 10px 0;
  line-height: 1.5;
}
.error:empty {
  display: none;
}
dialog {
  border: 0;
  border-radius: 16px;
  max-width: 450px;
  width: calc(100% - 40px);
  padding: 32px;
  box-shadow: 0 24px 80px #002e3240;
  color: #233544;
}
dialog::backdrop {
  background: #002f2abb;
  backdrop-filter: blur(4px);
}
dialog h2 {
  font:
    700 1.7rem Georgia,
    serif;
  margin: 15px 0 12px;
}
dialog p {
  line-height: 1.65;
  margin-bottom: 25px;
}
.dialog-icon {
  color: #09654b;
}
.dialog-icon .icon {
  width: 37px;
  height: 37px;
}
.reset-card {
  margin-top: 50px;
}
.reset-card h2 {
  font:
    700 2rem Georgia,
    serif;
  margin: 24px 0 12px;
  color: var(--dark);
}
.reset-card > p {
  line-height: 1.5;
  margin-bottom: 25px;
  color: #5c6e7c;
}
@media (min-width: 1400px) {
  .login-page {
    padding-top: 38px;
  }
  .login-heading {
    margin-bottom: 34px;
  }
  .login-card {
    padding-top: 30px;
  }
}
@media (max-width: 1100px) {
  .registration-shell {
    margin: 28px 24px;
    grid-template-columns: 29% 71%;
    min-height: 950px;
  }
  .registration-main {
    padding: 30px 28px;
  }
  .topbar p {
    max-width: 260px;
    line-height: 1.4;
  }
  .register-heading h1 {
    font-size: 2.4rem;
  }
  .brand-panel img {
    object-fit: cover;
  }
  .name-row {
    gap: 9px;
  }
  .name-row .input-wrap input {
    padding-left: 34px;
    font-size: 0.8rem;
  }
  .name-row .input-wrap > .icon {
    left: 9px;
    width: 17px;
  }
  .form-actions button {
    min-width: 150px;
  }
  .login-heading h1 {
    white-space: normal;
    margin: 0;
    font-size: clamp(2.1rem, 6.2vw, 4rem);
  }
}
@media (max-width: 760px) {
  .registration-shell {
    display: block;
    margin: 18px;
    min-height: 0;
  }
  .brand-panel {
    display: none;
  }
  .registration-main {
    padding: 25px 22px;
  }
  .topbar {
    font-size: 0.8rem;
  }
  .topbar p {
    max-width: 200px;
  }
  .register-heading {
    margin: 32px 0;
  }
  .register-heading h1 {
    font-size: 2.1rem;
  }
  .register-heading p {
    font-size: 1rem;
  }
  .steps li {
    font-size: 0.78rem;
  }
  .step-number {
    width: 37px;
    height: 37px;
    font-size: 1.2rem;
  }
  .steps li:before {
    top: 18px;
  }
  .banner {
    padding: 17px;
    gap: 16px;
  }
  .banner p {
    font-size: 0.9rem;
  }
  .banner > .icon {
    width: 32px;
    height: 32px;
  }
  .name-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .name-row .input-wrap input {
    font-size: 1rem;
    padding-left: 48px;
  }
  .name-row .input-wrap > .icon {
    width: 22px;
    left: 15px;
  }
  .role-field {
    width: 100%;
  }
  .form-actions button {
    min-width: 0;
    padding: 12px 21px;
  }
  .requirements {
    padding: 20px 17px;
    gap: 14px;
  }
  .requirements > .icon {
    width: 26px;
  }
  .requirements li {
    font-size: 0.9rem;
    margin-bottom: 5px;
    align-items: flex-start;
  }
  .rule-dot {
    margin-top: 3px;
  }
  .reminder {
    padding: 18px;
    gap: 14px;
  }
  .login-page {
    padding: 30px 18px;
  }
  .login-heading h1 {
    letter-spacing: -0.8px;
    font-size: 2.5rem;
  }
  .login-heading p {
    font-size: 1rem;
    margin-top: 15px;
  }
  .quick-actions {
    gap: 12px;
  }
  .quick-card {
    padding: 17px 13px;
    gap: 10px;
    min-height: 105px;
  }
  .quick-card strong {
    font-size: 1rem;
  }
  .quick-card span {
    font-size: 0.8rem;
  }
  .quick-card > .icon {
    width: 29px;
    height: 29px;
  }
  .quick-card.secondary > .icon:first-child {
    width: 36px;
    height: 36px;
    padding: 6px;
  }
  .quick-card > .chevron {
    width: 17px;
  }
  .login-card {
    padding: 25px 22px;
  }
  .login-description {
    font-size: 0.95rem;
  }
  .login-title {
    font-size: 1.6rem;
  }
  .campus {
    width: 25%;
    height: 40vh;
    background-size: auto 750px;
  }
  .detail-row {
    grid-template-columns: 40% 1fr;
    gap: 12px;
    font-size: 0.88rem;
  }
}
@media (prefers-reduced-motion: no-preference) {
  button,
  input,
  select {
    transition:
      background 0.15s,
      border-color 0.15s,
      box-shadow 0.15s;
  }
  #app {
    animation: appear 0.25s ease;
  }
  @keyframes appear {
    from {
      opacity: 0.6;
      transform: translateY(4px);
    }
    to {
      opacity: 1;
      transform: none;
    }
  }
}
:root {
  color-scheme: light;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
#page-title:focus {
  outline: none;
}
@media (max-width: 480px) {
  .quick-actions {
    grid-template-columns: 1fr;
  }
  .quick-card {
    min-height: 86px;
    padding: 17px 20px;
  }
  .quick-card strong {
    font-size: 1.12rem;
  }
  .quick-card span {
    font-size: 0.9rem;
  }
  .quick-card span br {
    display: none;
  }
  .quick-card > div {
    min-width: 0;
  }
  .login-heading h1 {
    font-size: 2.15rem;
  }
  .registration-shell {
    margin: 10px;
  }
  .registration-main {
    padding: 24px 16px;
  }
  .topbar {
    align-items: flex-start;
    gap: 12px;
  }
  .topbar p {
    font-size: 0.75rem;
  }
  .form-actions button {
    font-size: 0.95rem;
    padding: 12px 15px;
  }
  .requirements {
    gap: 10px;
    padding: 17px 12px;
  }
  .requirements > .icon {
    display: none;
  }
  .detail-row {
    grid-template-columns: 43% 1fr;
  }
  .reminder {
    gap: 12px;
    padding: 16px 13px;
  }
}

.campus-left,
.campus-right {
  width: 23%;
}
.quick-card > .chevron {
  padding: 0;
  background: none;
  border-radius: 0;
  width: 22px;
  height: 22px;
}

/* Password recovery: the three screens share the registration's campus panel. */
.forgot-shell {
  min-height: 1080px;
  margin-top: 60px;
  margin-bottom: 50px;
  grid-template-columns: 29.5% 70.5%;
}
.forgot-shell .registration-main {
  padding: 38px 40px 30px;
}
.forgot-shell .topbar p {
  margin-left: auto;
}
.forgot-shell .register-heading {
  margin: 44px 0 34px;
}
.forgot-shell .register-heading h1 {
  font-size: 2.7rem;
}
.forgot-shell .steps {
  margin-bottom: 30px;
}
.forgot-shell .steps li {
  font-size: 0.96rem;
}
.forgot-shell .banner {
  padding: 20px 27px;
  gap: 25px;
  margin-bottom: 27px;
  min-height: 102px;
}
.forgot-shell .banner strong {
  font-size: 1.16rem;
}
.forgot-shell .field {
  margin-bottom: 27px;
}
.forgot-shell .field > label {
  font-size: 1.06rem;
}
.forgot-shell .input-wrap input {
  height: 56px;
}
.forgot-shell .form-actions {
  margin-top: 27px;
}
.forgot-shell .form-actions button {
  min-width: 200px;
  min-height: 58px;
}
.forgot-shell .requirements {
  margin-top: 24px;
  padding: 22px 27px;
}
.forgot-shell .requirements li {
  line-height: 1.75;
}
.reset-step-1 .register-heading {
  margin-top: 58px;
  margin-bottom: 44px;
}
.reset-heading-icon {
  display: grid;
  place-items: center;
  border-radius: 50%;
  width: 96px;
  height: 96px;
  margin: 0 auto 18px;
  background: #e0efe8;
  color: #086046;
}
.reset-heading-icon .icon {
  width: 44px;
  height: 44px;
  stroke-width: 2.4;
}
.reset-step-1 .steps {
  margin-bottom: 40px;
}
.reset-send {
  margin-top: 8px;
  min-height: 61px;
}
.reset-step-1 .divider {
  margin: 31px 0;
  font-weight: 600;
  font-size: 0.92rem;
}
.reset-login {
  display: flex;
  justify-content: center;
}
.reset-login .outline {
  min-width: 266px;
  min-height: 57px;
}
.reset-credentials {
  background: #f2f5f7;
  border-radius: 10px;
  padding: 24px 30px;
  margin: 0 0 34px;
}
.reset-credential + .reset-credential {
  margin-top: 27px;
}
.reset-credential > label {
  display: block;
  font-weight: 650;
  margin-bottom: 8px;
}
.credential-value {
  display: flex;
  align-items: center;
  gap: 21px;
  background: #e6eaee;
  border-radius: 9px;
  padding: 0 17px;
  min-height: 58px;
}
.credential-value input {
  flex: 1;
  width: 0;
  min-width: 0;
  border: 0;
  background: transparent;
  color: #243a48;
  font-size: 1.1rem;
  padding: 14px 0;
  border-radius: 3px;
}
.credential-value input:focus {
  box-shadow: none;
  outline: 2px solid #25876a;
  outline-offset: 4px;
}
.credential-value > .icon {
  width: 25px;
  height: 25px;
}
.credential-highlight {
  background: #dcefe8;
  color: #185f53;
  min-height: 62px;
}
.credential-highlight input {
  font-weight: 700;
  font-size: 1.42rem;
  color: #185f53;
  letter-spacing: 0.3px;
}
.copy-button {
  display: grid;
  place-items: center;
  padding: 7px;
  color: inherit;
  background: none;
  border-radius: 5px;
  flex-shrink: 0;
}
.copy-button:hover {
  background: #00543f10;
}
.reset-credentials > p {
  font-size: 0.89rem;
  line-height: 1.6;
  margin-top: 12px;
}
.reset-credentials > p strong {
  font-weight: 600;
}
.copy-status {
  display: block;
  color: var(--green);
  font-size: 0.87rem;
  line-height: 1.5;
}
.copy-status:empty {
  display: none;
}
.reset-step-2 .form-actions {
  margin-top: 56px;
}
.reset-preview-note {
  color: #687984;
  font-size: 0.8rem;
  text-align: center;
  line-height: 1.5;
  margin-top: 29px;
}
.forgot-shell input[aria-invalid="true"] {
  border-color: #ac3232;
}
/* Keep recovery fixes scoped to these screens; shared login/registration stays unchanged. */
.forgot-shell .brand-crop img {
  object-fit: fill;
}
.forgot-shell .steps li {
  flex: 1 1 0;
  min-width: 0;
}
.forgot-shell .banner > div,
.forgot-shell .requirements > div {
  min-width: 0;
  overflow-wrap: anywhere;
}
.forgot-shell .reset-login .outline {
  min-width: 0;
  width: min(266px, 100%);
}
.forgot-shell .form-actions {
  flex-wrap: wrap;
}
.forgot-shell .form-actions button {
  max-width: 100%;
}
body:has(.forgot-shell) #notice {
  max-height: calc(100dvh - 32px);
  overflow: auto;
  overflow-wrap: anywhere;
}
@media (max-width: 1100px) {
  .forgot-shell {
    margin: 28px 24px;
  }
  .forgot-shell .registration-main {
    padding: 30px 28px;
  }
  .forgot-shell .register-heading h1 {
    font-size: 2.4rem;
  }
  .forgot-shell .form-actions button {
    min-width: 155px;
  }
  .forgot-shell .reset-credentials {
    padding: 23px;
  }
}
@media (max-width: 900px) {
  .forgot-shell {
    display: block;
    margin: 18px;
    min-height: 0;
  }
  .forgot-shell .brand-panel {
    display: none;
  }
  .forgot-shell .registration-main {
    padding: 25px 22px;
  }
  .forgot-shell .topbar {
    flex-wrap: wrap;
    row-gap: 12px;
  }
  .forgot-shell .register-heading {
    margin: 32px 0;
  }
  .forgot-shell .register-heading h1 {
    font-size: 2.1rem;
  }
  .forgot-shell .banner {
    padding: 17px;
    gap: 15px;
    min-height: 0;
  }
  .forgot-shell .banner strong {
    font-size: 1.06rem;
  }
  .forgot-shell .reset-heading-icon {
    width: 76px;
    height: 76px;
  }
  .forgot-shell .reset-heading-icon .icon {
    width: 36px;
    height: 36px;
  }
  .forgot-shell .reset-credentials {
    padding: 19px 16px;
  }
  .forgot-shell .credential-value {
    padding: 0 12px;
    gap: 12px;
  }
  .forgot-shell .credential-value input {
    font-size: 1rem;
  }
  .forgot-shell .credential-highlight input {
    font-size: 1.3rem;
  }
  .forgot-shell .form-actions button {
    min-width: 0;
    padding: 12px 23px;
  }
  .forgot-shell.reset-step-2 .form-actions {
    margin-top: 30px;
  }
  .forgot-shell .requirements {
    padding: 20px 17px;
  }
  .forgot-shell .requirements li {
    align-items: flex-start;
  }
  .forgot-shell .rule-dot {
    margin-top: 5px;
  }
  .forgot-shell .reset-send {
    font-size: 1rem;
    padding: 12px 15px;
  }
  .forgot-shell .reset-preview-note {
    font-size: 0.78rem;
  }
  .forgot-shell .copy-button,
  .forgot-shell .reveal {
    min-width: 44px;
    min-height: 44px;
  }
  .forgot-shell .password-input input {
    padding-right: 60px;
  }
}
@media (max-width: 480px) {
  .forgot-shell {
    margin: 10px;
  }
  .forgot-shell .registration-main {
    padding: 24px 16px;
  }
  .forgot-shell .topbar p {
    flex-basis: 100%;
    max-width: none;
    line-height: 1.5;
  }
  .forgot-shell .topbar p a {
    display: inline-block;
    max-width: 100%;
    overflow-wrap: anywhere;
  }
  .forgot-shell .steps li {
    font-size: 0.875rem;
    overflow-wrap: anywhere;
  }
  .forgot-shell .banner {
    padding: 17px 14px;
    gap: 13px;
  }
  .forgot-shell .requirements {
    padding: 17px 12px;
  }
  .forgot-shell .credential-value {
    gap: 9px;
    padding: 0 10px;
  }
  .forgot-shell .credential-value > .icon {
    width: 21px;
  }
  .forgot-shell .copy-button {
    padding: 6px;
  }
  .forgot-shell .reset-credentials {
    padding: 18px 13px;
  }
  .forgot-shell .reset-credential > label {
    font-size: 0.9rem;
  }
  .forgot-shell .reset-credentials > p {
    font-size: 0.85rem;
  }
  .forgot-shell.reset-step-1 .steps {
    margin-bottom: 29px;
  }
  body:has(.forgot-shell) #notice {
    padding: 24px;
  }
}
@media (max-width: 380px) {
  .forgot-shell .form-actions button {
    flex: 1 1 100%;
  }
  .forgot-shell .reset-login .outline {
    width: 100%;
  }
  .forgot-shell .register-heading h1 {
    font-size: 2rem;
  }
}

/* Keep the wide login title inside the viewport at small laptop widths. */
@media (min-width: 1101px) {
  .login-heading h1 {
    margin-inline: max(-150px, calc((860px - 100vw + 40px) / 2));
  }
}
/* Use the same reference-image crop and compact layout across account screens. */
.registration-shell:not(.forgot-shell) .brand-crop img {
  object-fit: fill;
}
.registration-shell .steps li {
  min-width: 0;
  flex: 1 1 0;
}
.registration-shell .banner > div,
.registration-shell .requirements > div {
  min-width: 0;
  overflow-wrap: anywhere;
}
.registration-shell .form-actions {
  flex-wrap: wrap;
}
.registration-shell .form-actions button {
  max-width: 100%;
}
dialog {
  max-height: calc(100dvh - 32px);
  overflow: auto;
  overflow-wrap: anywhere;
}
@media (max-width: 1100px) {
  .registration-shell .topbar p a {
    display: inline-block;
    max-width: 100%;
    overflow-wrap: anywhere;
  }
}
@media (max-width: 900px) {
  .registration-shell:not(.forgot-shell) {
    display: block;
    margin: 18px;
    min-height: 0;
  }
  .registration-shell:not(.forgot-shell) .brand-panel {
    display: none;
  }
  .registration-shell .topbar {
    flex-wrap: wrap;
    row-gap: 12px;
  }
  .registration-shell:not(.forgot-shell) .name-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .registration-shell:not(.forgot-shell) .name-row .input-wrap input {
    font-size: 1rem;
    padding-left: 48px;
  }
  .registration-shell:not(.forgot-shell) .name-row .input-wrap > .icon {
    width: 22px;
    left: 15px;
  }
  .registration-shell:not(.forgot-shell) .role-field {
    width: 100%;
  }
  .reveal {
    min-width: 44px;
    min-height: 44px;
  }
  .password-input input {
    padding-right: 60px;
  }
}
@media (max-width: 480px) {
  .registration-shell:not(.forgot-shell) {
    margin: 10px;
  }
  .registration-shell .topbar p {
    flex-basis: 100%;
    max-width: none;
    line-height: 1.5;
  }
  dialog {
    padding: 24px;
  }
}
@media (max-width: 380px) {
  .registration-shell .form-actions button {
    flex: 1 1 100%;
  }
}

/* 7.2.2 — logout-modal */
#logoutOverlay {
  width: min(440px, calc(100% - 32px));
  max-width: 440px;
  max-height: calc(100dvh - 32px);
  padding: 0;
  border: 1px solid #e1eae6;
  border-radius: 16px;
  background: #fff;
  color: #173b45;
  box-shadow: 0 20px 60px #002c3540;
  text-align: left;
  overflow: auto;
}
#logoutOverlay::backdrop {
  background: #062f3699;
}
.logout-modal-content {
  position: relative;
  padding: 30px;
}
#closeLogout {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  background: transparent;
  border-radius: 8px;
  color: #506a65;
  font-size: 26px;
}
#closeLogout:hover {
  background: #edf5f1;
}
.logout-modal-icon {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  background: #e7f3ed;
  color: #075a43;
  border-radius: 50%;
  margin-bottom: 18px;
}
#logoutTitle {
  font:
    700 27px/1.2 Georgia,
    serif;
  margin: 0 36px 12px 0;
  color: #123e47;
}
#logoutDescription {
  font-size: 16px;
  line-height: 1.6;
  color: #64748a;
  margin: 0;
}
.logout-modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 26px;
}
.logout-modal-actions button {
  flex: 1;
  min-width: 0;
  min-height: 46px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
}
#cancelLogout {
  background: #fff;
  border: 1px solid #c9d6d2;
  color: #245448;
}
body.logout-modal-open {
  overflow: hidden;
}
@media (max-width: 400px) {
  .logout-modal-content {
    padding: 24px 20px;
  }
  .logout-modal-actions {
    gap: 10px;
  }
}

/* 7.2.3 — button-contrast */
body .primary {
  background: #07543f;
  color: #fff;
  border: 1px solid #064332;
  font-weight: 600;
}
body .outline {
  background: #fff;
  color: #153e35;
  border: 1px solid #557a70;
  font-weight: 600;
}
body .reveal,
body .copy-button {
  color: #244b41;
}
body .dash-shell .dash-nav a,
body .dash-shell .dash-nav button,
body .dash-shell .dash-logout,
body .admin-ui .nav-item,
body .admin-ui .nav-item:nth-of-type(5),
body .admin-ui .logout-link {
  color: #fff;
  font-weight: 600;
}
body .dash-shell .dash-nav .is-active {
  background: #286456;
  box-shadow: inset 3px 0 #c0efdf;
}
body .admin-ui .nav-item.active {
  background: #064bd8;
  box-shadow: inset 3px 0 #baceff;
}
body .dash-shell .dash-nav a:hover,
body .dash-shell .dash-nav button:hover,
body .dash-shell .dash-logout:hover {
  background: #286456;
}
body .admin-ui .nav-item:hover,
body .admin-ui .logout-link:hover {
  background: #203b69;
}
body .dash-shell .dash-icon-button:not(.dash-sidebar-close),
body .admin-ui #admin-menu,
body .admin-ui .notification {
  color: #123d39;
  background: #edf3f4;
  border: 1px solid #6c8583;
  border-radius: 8px;
}
body .dash-shell .dash-sidebar-close,
body .admin-ui .admin-close {
  color: white;
  background: #ffffff18;
  border: 1px solid #a5bbb9;
  border-radius: 8px;
}
body .dash-shell .dash-quick-grid button {
  border: 1px solid var(--tone);
  font-weight: 600;
}
body .dash-shell .dash-panel-heading > button {
  color: #07543f;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
}
body .dash-shell .dash-profile-menu button {
  color: #123d39;
  font-weight: 600;
}
body .admin-ui .view-btn,
body .admin-ui .view-all,
body .admin-ui .date-selector,
body .admin-ui .students-button,
body .student-dialog button {
  color: #143e9c;
  border: 1px solid #5075ad;
  background: #f4f7ff;
  font-weight: 600;
}
body .admin-ui .stat-link {
  color: #0644b8;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
}
body .admin-ui .students-primary,
body .student-dialog .students-primary {
  background: #1249bd;
  color: #fff;
  border-color: #103c97;
}
body .admin-ui .students-danger,
body .student-dialog .students-danger {
  color: #a51d30;
  background: #fff2f3;
  border-color: #b63749;
}
body .admin-ui .students-button:disabled {
  opacity: 1;
  color: #596579;
  background: #e5e9f0;
  border-color: #8994a6;
  cursor: not-allowed;
}
body #recollection-root .recollection-receipt-actions button {
  color: #07543f;
  border-color: #397763;
  font-weight: 600;
}
body #recollection-root .recollection-receipt-actions #receipt-remove {
  color: #a51d30;
  border-color: #b63749;
  background: #fff2f3;
}
body #cancelLogout {
  color: #153e35;
  border-color: #557a70;
  background: #f4f8f6;
}
body #closeLogout {
  color: #153e35;
  border: 1px solid #6c8583;
  background: #f4f8f6;
}
body #confirmLogout {
  color: #fff;
  background: #a92232;
  border-color: #911b29;
}
body button:focus-visible,
body a:focus-visible {
  outline: 3px solid #176aa1;
  outline-offset: 3px;
}
body .dash-sidebar button:focus-visible,
body .dash-sidebar a:focus-visible,
body .admin-ui .sidebar button:focus-visible,
body .admin-ui .sidebar a:focus-visible {
  outline: 2px solid #fff;
  outline-offset: -3px;
}

/* 7.2.4 — dashboard */
/* Student dashboard. Styles are scoped to preserve the existing account screens. */
/* Reserve the page scrollbar space on both short and tall student screens.
   This prevents the header and content from shifting when a route needs scrolling. */
html:has(> body.dashboard-mode) {
  scrollbar-gutter: stable;
}
@supports not (scrollbar-gutter: stable) {
  html:has(> body.dashboard-mode) {
    overflow-y: scroll;
  }
}
.dashboard-mode {
  background: #eff6f8;
  color: #123740;
}
.dashboard-mode > .campus {
  display: none;
}
.dashboard-mode #app {
  animation: none;
}
.dashboard-mode #notice-text {
  white-space: pre-line;
}
.dashboard-drawer-open {
  overflow: hidden;
}
.dash-shell {
  --sidebar-width: 307px;
  min-height: 100dvh;
  font-family: "Segoe UI", Arial, sans-serif;
}
.dash-shell button,
.dash-shell a {
  touch-action: manipulation;
}
.dash-shell button {
  font-weight: 500;
}
.dash-shell [hidden] {
  display: none !important;
}
.dash-sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  width: var(--sidebar-width);
  background: radial-gradient(ellipse at 0 60%, #14534b, transparent 65%), #083f35;
  color: #fff;
  z-index: 30;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  isolation: isolate;
}
/* Display only the campus region of the reference, excluding its navigation and text. */
.dash-sidebar:before {
  content: "";
  position: absolute;
  inset: auto 0 0;
  height: 285px;
  background: var(--dashboard-reference) left -1119px/1536px 1536px no-repeat;
  mask-image: linear-gradient(transparent, #000 40%);
  z-index: -1;
  pointer-events: none;
}
.dash-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  color: #fff;
  padding: 26px 16px 22px;
  font-family: Georgia, "Times New Roman", serif;
  flex-shrink: 0;
}
.dash-seal {
  display: block;
  width: 114px;
  height: 114px;
  border-radius: 50%;
  background: url("{{ asset('assets/ulslogo.png') }}") center / contain no-repeat;
  margin-bottom: 8px;
}
.dash-university {
  font-size: 20px;
  line-height: 1.14;
}
.dash-university strong {
  font-size: 23px;
  font-weight: 600;
}
.dash-brand-subtitle {
  font-size: 19px;
  margin-top: 6px;
}
.dash-nav {
  display: grid;
  gap: 0;
  padding: 0 17px;
}
.dash-nav a,
.dash-nav button,
.dash-logout {
  display: flex;
  align-items: center;
  gap: 18px;
  min-height: 49px;
  padding: 12px 13px;
  color: #fff;
  background: none;
  border-radius: 11px;
  font-size: 16px;
  text-align: left;
  line-height: 1.4;
  text-decoration: none;
  min-width: 0;
}
.dash-nav .icon,
.dash-logout .icon {
  width: 25px;
  height: 25px;
}
.dash-nav .is-active {
  background: #ffffff20;
  font-weight: 600;
}
.dash-nav button:hover,
.dash-nav a:hover,
.dash-logout:hover {
  background: #ffffff16;
  filter: none;
}
.dash-nav span {
  min-width: 0;
}
.dash-logout {
  border-top: 1px solid #71b6a7;
  border-radius: 0;
  margin: 36px 26px 0;
  padding: 20px 4px;
}
.dash-sidebar-quote {
  margin: auto 12px 34px;
  padding-top: 180px;
  text-align: center;
  font:
    18px/1.45 Georgia,
    serif;
  color: #fff;
}
.dash-main-pane {
  margin-left: var(--sidebar-width);
  min-width: 0;
}
.dash-topbar {
  height: 73px;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #e3edef;
  background: #ffffffb8;
  gap: 16px;
}
.dash-icon-button {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: none;
  color: #0d3d3c;
  flex-shrink: 0;
  padding: 9px;
}
.dash-icon-button .icon {
  width: 25px;
  height: 25px;
}
.dash-icon-button:hover {
  background: #e4eeeb;
}
.dash-user-tools {
  display: flex;
  align-items: center;
  gap: 20px;
  min-width: 0;
}
.dash-notifications {
  position: relative;
}
.dash-notifications span {
  position: absolute;
  top: 0;
  right: 0;
  border-radius: 50%;
  background: #ee4148;
  color: #fff;
  font-size: 11px;
  width: 19px;
  height: 19px;
  display: grid;
  place-items: center;
}
.dash-notification-area { position: relative; }
#student-notification-count[hidden], #student-notification-panel[hidden] { display: none !important; }
#student-notification-count { min-width: 19px; width: auto; padding: 0 4px; border-radius: 10px; }
.dash-status:focus { outline: 3px solid #27846b; outline-offset: 5px; }
.dash-notification-panel {
  position: absolute;
  z-index: 30;
  top: calc(100% + 12px);
  right: 0;
  width: min(390px, calc(100vw - 32px));
  max-height: min(72vh, 580px);
  overflow: auto;
  padding: 14px;
  border: 1px solid #dce7e3;
  border-radius: 14px;
  background: #fff;
  color: #173b45;
  box-shadow: 0 18px 48px #002c3530;
}
.dash-notification-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 3px 4px 12px;
  border-bottom: 1px solid #e4ece9;
}
.dash-notification-heading h2 { font-size: 16px; }
.dash-notification-heading span { color: #61756f; font-size: 12px; }
.dash-notification-list { display: grid; gap: 5px; padding-top: 7px; }
.dash-notification-item {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) 8px;
  align-items: start;
  gap: 10px;
  padding: 11px 8px;
  border-radius: 10px;
  color: #233544;
}
.dash-notification-item:hover,
.dash-notification-item.is-unviewed { background: #eff7f3; }
.dash-notification-icon {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border-radius: 9px;
  background: #e3f0eb;
  color: #075a43;
}
.dash-notification-icon .icon { width: 18px; height: 18px; }
.dash-notification-copy {
  min-width: 0;
  font-size: 13px;
  line-height: 1.45;
  overflow-wrap: anywhere;
}
.dash-notification-copy time {
  display: block;
  margin-top: 4px;
  color: #73817c;
  font-size: 11px;
}
.dash-notification-unread {
  width: 8px;
  height: 8px;
  margin-top: 5px;
  border-radius: 50%;
  background: #d34f45;
}
.dash-notification-empty {
  padding: 18px 8px;
  color: #73817c;
  font-size: 13px;
  text-align: center;
}
.dash-profile {
  position: relative;
  padding-left: 21px;
  border-left: 1px solid #e2e9eb;
  min-width: 0;
}
.dash-profile-button {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 0;
  background: none;
  color: #123740;
  text-align: left;
  min-height: 48px;
}
.dash-avatar {
  display: grid;
  place-items: center;
  border-radius: 50%;
  width: 43px;
  height: 43px;
  background: linear-gradient(135deg, #06473a, #146153);
  color: #fff;
  font-size: 20px;
  flex-shrink: 0;
}
.dash-profile-name {
  display: grid;
  gap: 3px;
  min-width: 0;
}
.dash-profile-name strong {
  font-size: 16px;
  font-weight: 650;
}
.dash-profile-name > span {
  font-size: 14px;
  color: #64748a;
}
.dash-profile-button > .icon {
  width: 18px;
  height: 18px;
  margin-left: 24px;
}
.dash-profile-menu {
  position: absolute;
  right: 0;
  top: 57px;
  width: 200px;
  padding: 8px;
  border: 1px solid #e0e8e7;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 10px 30px #113f4020;
  z-index: 20;
}
.dash-profile-menu button,
.dash-profile-menu a {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px;
  background: none;
  color: #123740;
  border-radius: 6px;
  font-size: 15px;
  text-align: left;
}
.dash-profile-menu button:hover,
.dash-profile-menu a:hover {
  background: #edf5f1;
}
.dash-content {
  padding: 23px 20px 12px 24px;
  max-width: 1700px;
  margin: auto;
}
.dash-welcome {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 25px;
}
.dash-greeting {
  font:
    28px/1.2 Georgia,
    "Times New Roman",
    serif;
}
.dash-welcome h1 {
  font:
    700 40px/1.17 Georgia,
    "Times New Roman",
    serif;
  color: #073d39;
  margin-top: 2px;
}
.dash-subtitle {
  font-size: 18px;
  color: #68788d;
  margin-top: 6px;
  line-height: 1.5;
}
.dash-welcome blockquote {
  font:
    italic 20px/1.35 Georgia,
    serif;
  text-align: center;
  margin: 5px 29px 0 0;
  flex-shrink: 0;
}
.dash-welcome cite {
  display: block;
  margin-top: 8px;
  font:
    normal 14px/1.5 "Segoe UI",
    Arial,
    sans-serif;
}
.dash-status-grid {
  display: grid;
  grid-template-columns: 1fr 0.95fr 1.05fr 1.02fr 1.02fr;
  gap: 18px;
  margin-bottom: 18px;
}
.dash-green {
  --tone: #00643d;
  --tint: #e6f5ed;
}
.dash-gold {
  --tone: #ce8b00;
  --tint: #fbf5e8;
}
.dash-blue {
  --tone: #00549e;
  --tint: #e4f2ff;
}
.dash-purple {
  --tone: #552581;
  --tint: #eee9fa;
}
.dash-rose {
  --tone: #ac1828;
  --tint: #fbeef2;
}
.dash-status {
  min-width: 0;
  min-height: 196px;
  padding: 25px 25px 23px;
  border: 2px solid #fff;
  border-radius: 15px;
  background: linear-gradient(115deg, var(--tint), color-mix(in srgb, var(--tint) 76%, white));
  color: var(--tone);
}
.dash-status > .dash-status-icon {
  width: 33px;
  height: 33px;
  margin-bottom: 17px;
  stroke-width: 2.7;
}
.dash-status h2 {
  font-size: 15px;
  font-weight: 400;
  color: #12313f;
  line-height: 1.4;
  margin: 0 0 4px;
}
.dash-status p {
  font-size: 20px;
  font-weight: 700;
  line-height: 1.25;
  overflow-wrap: anywhere;
}
.dash-status.dash-blue p {
  font-size: 18px;
}
.dash-status-mark {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  margin-top: 13px;
  border-radius: 50%;
  background: var(--tone);
  color: white;
}
.dash-status-mark .icon {
  width: 22px;
  height: 22px;
}
.dash-status-mark.is-calendar {
  background: none;
  color: var(--tone);
}
.dash-status-mark.is-calendar .icon {
  width: 28px;
  height: 28px;
  stroke-width: 2.6;
}
.dash-columns {
  display: grid;
  grid-template-columns: minmax(0, 1.75fr) minmax(0, 1fr);
  gap: 17px;
  align-items: start;
}
.dash-primary-column,
.dash-secondary-column {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.dash-panel {
  background: #ffffffed;
  border-radius: 15px;
  padding: 23px 26px;
  box-shadow: 0 3px 14px #254c5710;
  min-width: 0;
}
.dash-panel h2 {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 22px;
  line-height: 1.35;
  font-weight: 700;
  color: #123740;
  margin: 0;
}
.dash-panel h2 > .icon {
  width: 27px;
  height: 27px;
}
.dash-event-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.03fr);
  gap: 27px;
  margin-top: 18px;
}
.dash-chapel {
  margin: 0;
  min-width: 0;
}
.dash-chapel-image {
  aspect-ratio: 325/223;
  position: relative;
  overflow: hidden;
  border-radius: 11px;
  background: #b4a17e;
}
.dash-chapel-image img {
  position: absolute;
  max-width: none;
  width: 472.6154%;
  height: 459.1929%;
  left: -110.1538%;
  top: -230.0448%;
}
.dash-chapel figcaption {
  text-align: center;
  margin-top: 15px;
  font:
    italic 18px/1.4 Georgia,
    serif;
}
.dash-chapel figcaption span {
  display: block;
  font:
    normal 14px/1.5 "Segoe UI",
    Arial,
    sans-serif;
  margin-top: 6px;
}
.dash-event-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.dash-event-info dl {
  display: grid;
  gap: 25px;
  margin: 10px 0 27px;
}
.dash-event-info dl > div {
  display: grid;
  grid-template-columns: 25px 61px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
  font-size: 16px;
  line-height: 1.5;
}
.dash-event-info dt {
  font-weight: 600;
}
.dash-event-info dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.dash-event-info .icon {
  width: 24px;
  height: 24px;
  stroke-width: 2.4;
}
.dash-scheduled {
  display: inline-block;
  border-radius: 25px;
  padding: 5px 15px;
  color: #005838;
  background: #c5e7d5;
  font-size: 14px;
}
.dash-view-schedule {
  width: 100%;
  margin-top: auto;
  min-height: 57px;
  gap: 16px;
  font-size: 18px !important;
  font-weight: 600 !important;
  padding: 12px;
}
.dash-reflection {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 20px;
  min-height: 131px;
  padding: 25px 23px;
  border: 2px solid #fff;
  border-radius: 15px;
  background: #ffffff42;
}
.dash-leaf {
  display: grid;
  place-items: center;
  width: 51px;
  height: 51px;
  flex-shrink: 0;
  border-radius: 50%;
  background: #cce6dc;
  color: #096245;
}
.dash-leaf .icon {
  width: 28px;
  height: 28px;
}
.dash-reflection p {
  font:
    italic 20px/1.5 Georgia,
    serif;
  margin-bottom: 5px;
}
.dash-reflection span:not(.dash-leaf) {
  font-size: 15px;
  color: #66778c;
}
.dash-reflection > .dash-leaf-decoration {
  position: absolute;
  width: 130px;
  height: 130px;
  right: -5px;
  bottom: -25px;
  color: #7fbaaa18;
  z-index: -1;
  transform: rotate(-28deg);
}
.dash-announcements,
.dash-quick {
  padding: 17px 17px 16px;
}
.dash-panel-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.dash-secondary-column h2 {
  font-size: 19px;
  gap: 12px;
}
.dash-panel-heading > button {
  background: none;
  color: #00643d;
  min-height: 32px;
  padding: 4px 0;
  font-size: 14px;
  white-space: nowrap;
}
.dash-announcement {
  display: flex;
  gap: 13px;
  padding: 12px 13px;
  border-radius: 11px;
  background: var(--tint);
  color: #23424d;
}
.dash-announcement + .dash-announcement {
  margin-top: 10px;
}
.dash-announcement > .icon {
  width: 19px;
  height: 19px;
  color: var(--tone);
  margin-top: 4px;
}
.dash-announcement > div {
  min-width: 0;
}
.dash-announcement h3 {
  font-size: 15px;
  line-height: 1.4;
  margin: 0 0 2px;
  color: #103933;
}
.dash-announcement p {
  font-size: 14px;
  line-height: 1.55;
}
.dash-announcement time {
  display: block;
  font-size: 12px;
  color: #5e7480;
  line-height: 1.4;
  margin-top: 4px;
}
.dash-quick h2 {
  margin-bottom: 15px;
}
.dash-quick-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 13px;
}
.dash-quick-grid button {
  display: flex;
  align-items: center;
  gap: 15px;
  background: var(--tint);
  color: var(--tone);
  border-radius: 12px;
  padding: 16px;
  min-height: 70px;
  text-align: left;
  font-size: 14px;
  line-height: 1.5;
}
.dash-quick-grid .icon {
  width: 29px;
  height: 29px;
  stroke-width: 2.5;
}
.dash-preview-note {
  font-size: 11px;
  color: #71858c;
  text-align: right;
  margin-top: 10px;
  line-height: 1.5;
}
.dash-sidebar-close,
.dash-backdrop {
  display: none;
}
.dash-nav-hidden .dash-sidebar {
  transform: translateX(-100%);
}
.dash-nav-hidden .dash-main-pane {
  margin-left: 0;
}
@media (min-width: 1101px) and (max-width: 1400px) {
  .dash-shell {
    --sidebar-width: 264px;
  }
  .dash-nav a,
  .dash-nav button {
    gap: 13px;
    font-size: 14px;
  }
  .dash-status-grid {
    gap: 12px;
  }
  .dash-status {
    padding: 20px 16px;
  }
  .dash-status p {
    font-size: 18px;
  }
  .dash-columns {
    grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  }
  .dash-panel {
    padding: 20px;
  }
  .dash-event-grid {
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 18px;
  }
  .dash-event-info dl > div {
    grid-template-columns: 22px 43px minmax(0, 1fr);
    gap: 9px;
    font-size: 14px;
  }
  .dash-panel h2 {
    font-size: 20px;
  }
  .dash-secondary-column h2 {
    font-size: 17px;
    gap: 8px;
  }
  .dash-quick-grid button {
    gap: 10px;
    padding: 13px 10px;
    font-size: 13px;
  }
  .dash-welcome blockquote {
    margin-right: 0;
    font-size: 18px;
  }
}
@media (max-width: 1100px) {
  .dash-main-pane {
    margin-left: 0;
  }
  .dash-sidebar {
    transform: translateX(-100%);
    width: min(307px, calc(100vw - 40px));
  }
  .dash-nav-open .dash-sidebar {
    transform: translateX(0);
  }
  .dash-sidebar-close {
    display: grid;
    position: absolute;
    top: 8px;
    right: 6px;
    color: white;
  }
  .dash-sidebar-close:hover {
    background: #ffffff20;
  }
  .dash-backdrop:not([hidden]) {
    display: block;
    position: fixed;
    inset: 0;
    background: #062f36a6;
    z-index: 25;
    width: 100%;
    height: 100%;
    padding: 0;
  }
  .dash-content {
    padding: 24px;
  }
  .dash-status-grid {
    gap: 12px;
  }
  .dash-status {
    padding: 20px 16px;
  }
  .dash-status p {
    font-size: 18px;
  }
  .dash-columns {
    grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr);
  }
  .dash-panel {
    padding: 20px;
  }
  .dash-event-grid {
    gap: 18px;
  }
  .dash-event-info dl > div {
    grid-template-columns: 22px 45px minmax(0, 1fr);
    gap: 9px;
    font-size: 14px;
  }
  .dash-secondary-column h2 {
    font-size: 17px;
    gap: 8px;
  }
  .dash-quick-grid button {
    padding: 12px;
    gap: 9px;
    font-size: 13px;
  }
  .dash-welcome blockquote {
    margin-right: 0;
  }
}
@media (max-width: 900px) {
  .dash-status-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .dash-status {
    min-height: 172px;
  }
  .dash-columns {
    grid-template-columns: minmax(0, 1fr);
  }
  .dash-secondary-column {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
  }
  .dash-welcome blockquote {
    font-size: 17px;
  }
  .dash-welcome h1 {
    font-size: 36px;
  }
  .dash-subtitle {
    font-size: 16px;
  }
  .dash-quick-grid {
    grid-template-columns: 1fr;
  }
  .dash-quick-grid button {
    min-height: 54px;
  }
}
@media (max-width: 600px) {
  .dash-topbar {
    height: 68px;
    padding: 0 15px;
  }
  .dash-user-tools {
    gap: 10px;
  }
  .dash-profile {
    padding-left: 12px;
  }
  .dash-profile-name {
    display: none;
  }
  .dash-profile-button {
    gap: 8px;
  }
  .dash-profile-button > .icon {
    margin-left: 0;
  }
  .dash-content {
    padding: 22px 16px 14px;
  }
  .dash-welcome {
    display: block;
    margin-bottom: 20px;
  }
  .dash-welcome h1 {
    font-size: 34px;
  }
  .dash-greeting {
    font-size: 24px;
  }
  .dash-welcome blockquote {
    text-align: left;
    margin: 16px 0 0;
    font-size: 16px;
  }
  .dash-welcome blockquote br {
    display: none;
  }
  .dash-welcome cite {
    font-size: 12px;
    margin-top: 4px;
  }
  .dash-status-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .dash-status {
    padding: 18px 15px;
    min-height: 180px;
  }
  .dash-status h2 {
    font-size: 14px;
  }
  .dash-status p {
    font-size: 18px;
  }
  .dash-panel {
    padding: 20px 17px;
  }
  .dash-panel h2 {
    font-size: 20px;
    gap: 12px;
  }
  .dash-event-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 22px;
  }
  .dash-event-info dl {
    gap: 20px;
    margin-top: 0;
  }
  .dash-event-info dl > div {
    grid-template-columns: 24px 55px minmax(0, 1fr);
    gap: 13px;
    font-size: 15px;
  }
  .dash-secondary-column {
    grid-template-columns: minmax(0, 1fr);
  }
  .dash-quick-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .dash-quick-grid button {
    min-height: 72px;
  }
  .dash-reflection {
    padding: 22px 17px;
    gap: 14px;
  }
  .dash-reflection p {
    font-size: 18px;
  }
  .dash-preview-note {
    text-align: center;
  }
}
@media (max-width: 360px) {
  .dash-content {
    padding-inline: 12px;
  }
  .dash-welcome h1 {
    font-size: 30px;
  }
  .dash-status {
    padding: 15px 12px;
  }
  .dash-status p {
    font-size: 17px;
  }
  .dash-panel h2 {
    font-size: 18px;
    gap: 9px;
  }
  .dash-quick-grid button {
    padding: 12px 9px;
    gap: 8px;
  }
  .dash-quick-grid .icon {
    width: 23px;
    height: 23px;
  }
  .dash-event-info dl > div {
    gap: 9px;
    grid-template-columns: 22px 46px minmax(0, 1fr);
  }
  .dash-panel-heading {
    flex-wrap: wrap;
  }
}
@media (prefers-reduced-motion: no-preference) {
  .dash-sidebar {
    transition: transform 0.2s ease;
  }
}

/* Keep navigation legible and reserve space for Logout on short screens. */
.dash-sidebar {
  overscroll-behavior: contain;
  scrollbar-color: #83b7a9 #083f35;
}
.dash-nav {
  flex-shrink: 0;
}
.dash-nav a,
.dash-nav button,
.dash-logout {
  white-space: normal;
  overflow-wrap: break-word;
  font-weight: 500;
}
.dash-nav .icon,
.dash-logout .icon {
  flex-shrink: 0;
}
.dash-nav a:focus-visible,
.dash-nav button:focus-visible,
.dash-logout:focus-visible {
  outline: 2px solid white;
  outline-offset: -3px;
  background: #ffffff25;
}
@media (max-height: 900px) {
  .dash-sidebar {
    overflow: hidden;
    padding-bottom: env(safe-area-inset-bottom);
  }
  .dash-brand {
    padding: 14px 24px 12px;
  }
  .dash-seal {
    width: 76px;
    height: 76px;
    background-position: center;
    background-size: contain;
  }
  .dash-university {
    font-size: 17px;
  }
  .dash-university strong {
    font-size: 20px;
  }
  .dash-brand-subtitle {
    font-size: 16px;
  }
  .dash-nav {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding-bottom: 8px;
  }
  .dash-nav a,
  .dash-nav button {
    flex-shrink: 0;
    min-height: 44px;
    padding: 10px 12px;
    gap: 12px;
  }
  .dash-logout {
    flex-shrink: 0;
    margin: 8px 26px 8px;
    padding: 12px 4px;
    min-height: 48px;
  }
  .dash-sidebar-quote {
    display: none;
  }
}
@media (max-height: 500px) {
  .dash-brand {
    padding: 12px 40px;
  }
  .dash-seal {
    width: 48px;
    height: 48px;
  }
  .dash-brand-subtitle {
    display: none;
  }
  .dash-university br {
    display: none;
  }
  .dash-university,
  .dash-university strong {
    font-size: 16px;
  }
}

/* 7.2.5 — recollection */
/* Supplied two-section recollection form; account and dashboard layouts are unchanged. */
.recollection-mode .dash-shell {
  --sidebar-width: 277px;
}
.recollection-mode .dash-content {
  padding: 18px;
  max-width: 1600px;
}
.recollection-mode .dash-topbar {
  height: 54px;
}
.recollection-mode .dash-nav {
  padding-inline: 12px;
}
.recollection-mode .dash-nav a,
.recollection-mode .dash-nav button {
  font-size: 14px;
  gap: 12px;
  padding-inline: 10px;
}
.recollection-mode .dash-brand {
  padding-bottom: 35px;
}
.recollection-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 18px;
}
#recollection-root {
  color: #132e40;
  font-size: 14px;
  min-width: 0;
}
.recollection-header,
.recollection-form {
  background: #ffffffed;
  border: 1px solid #e4ebee;
  border-radius: 13px;
  box-shadow: 0 4px 12px #123b4710;
}
.recollection-header {
  padding: 24px 26px 20px;
  margin-bottom: 14px;
}
.recollection-heading-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
}
.recollection-heading-row > div {
  min-width: 0;
}
.recollection-header h1 {
  font:
    700 32px/1.2 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #103543;
  margin: 0;
}
.recollection-header h1:focus {
  outline: none;
}
.recollection-header p {
  font-size: 15px;
  color: #7b86a2;
  line-height: 1.5;
  margin-top: 3px;
}
#recollection-root .outline,
#recollection-root .primary {
  font-size: 13px;
  min-height: 42px;
  padding: 10px 19px;
  gap: 10px;
  border-radius: 7px;
}
#recollection-root .recollection-back {
  flex-shrink: 0;
  min-height: 38px;
  white-space: nowrap;
}
.recollection-steps {
  display: flex;
  list-style: none;
  padding: 0;
  margin: 30px auto 0;
  max-width: 720px;
}
.recollection-steps li {
  position: relative;
  flex: 1 1 0;
  min-width: 0;
  text-align: center;
  font-size: 14px;
  line-height: 1.45;
  color: #7e88a2;
}
.recollection-steps li:before {
  content: "";
  position: absolute;
  top: 19px;
  right: 50%;
  width: 100%;
  height: 2px;
  background: #c5cbd8;
}
.recollection-steps li:first-child:before {
  display: none;
}
.recollection-steps li.is-active:before {
  background: linear-gradient(to right, #c5cbd8, #087054);
}
.recollection-steps li > span {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  margin: 0 auto 7px;
  background: #b6bdcd;
  color: #fff;
  border-radius: 50%;
  font-size: 23px;
  font-weight: 600;
}
.recollection-steps .is-active {
  color: #005d43;
  font-weight: 600;
}
.recollection-steps .is-active > span {
  background: linear-gradient(135deg, #09684e, #003f33);
}
.recollection-form {
  padding: 12px 15px 16px;
}
.recollection-section-heading {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: linear-gradient(90deg, #edf6f2, #f1f8f6);
  padding: 16px 15px;
  border-radius: 7px;
  margin-bottom: 12px;
}
.recollection-section-heading > .icon {
  width: 29px;
  height: 29px;
  color: #00604a;
  flex-shrink: 0;
  margin-top: 2px;
}
.recollection-section-heading h2 {
  font:
    700 23px/1.25 Georgia,
    serif;
  color: #0b403a;
}
.recollection-section-heading p {
  color: #72819a;
  font-size: 14px;
  line-height: 1.5;
  margin-top: 4px;
}
.recollection-group {
  min-width: 0;
  border: 1px solid #dceae7;
  border-radius: 8px;
  overflow: hidden;
  margin: 0 0 16px;
  background: #fff;
  box-shadow: 0 2px 5px #193f4910;
}
.recollection-group h3 {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 0;
  padding: 11px 14px;
  background: linear-gradient(90deg, #edf6f2, #f0f8f5);
  font-size: 16px;
  line-height: 1.4;
  font-weight: 700;
}
.recollection-group h3 > .icon {
  width: 25px;
  height: 25px;
  color: #05634c;
  flex-shrink: 0;
}
.recollection-group-body {
  padding: 13px 14px 16px;
  display: grid;
  gap: 16px;
  min-width: 0;
}
.recollection-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  min-width: 0;
}
.recollection-personal-row {
  grid-template-columns: 1.2fr 1fr 0.76fr;
}
.recollection-thirds {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 35px;
}
.recollection-field {
  min-width: 0;
}
.recollection-field label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.4;
  margin-bottom: 5px;
}
.recollection-field input,
.recollection-field select,
.recollection-field textarea {
  font: inherit;
  font-size: 14px;
  color: #162d41;
  display: block;
  width: 100%;
  min-width: 0;
  border: 1px solid #c5ccdd;
  border-radius: 5px;
  background: #fdfdff;
  padding: 9px 12px;
  min-height: 39px;
}
.recollection-field input::placeholder,
.recollection-field textarea::placeholder {
  color: #8590a9;
  opacity: 1;
}
.recollection-field select {
  padding-right: 28px;
}
.recollection-field select:has(option:checked[value=""]) {
  color: #7785a1;
}
.recollection-field textarea {
  resize: vertical;
  min-height: 57px;
  max-width: 100%;
  line-height: 1.5;
}
.recollection-field textarea:focus {
  outline: none;
  border-color: #27846b;
  box-shadow: 0 0 0 3px #27846b18;
}
.recollection-academic-fields {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr;
  gap: 16px;
  min-width: 0;
}
.recollection-academic-field {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}
.recollection-academic-field legend {
  padding: 0;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
}
.recollection-academic-field select {
  display: block;
  width: 100%;
  min-width: 0;
  min-height: 39px;
  padding: 9px 12px;
  border: 1px solid #c5ccdd;
  border-radius: 5px;
  background: #fdfdff;
  color: #162d41;
  font: inherit;
  font-size: 14px;
  cursor: pointer;
}
.recollection-academic-field select:has(option:checked[value=""]) {
  color: #7785a1;
}
.recollection-academic-hint,
.recollection-academic-update {
  font-size: 13px;
  line-height: 1.5;
  color: #61738a;
}
.recollection-academic-hint { margin-top: 8px; }
.recollection-academic-update:not(:empty) {
  padding: 12px 14px;
  margin-bottom: 16px;
  border-radius: 7px;
  background: #edf6f1;
  color: #075a43;
}
@media (max-width: 760px) {
  .recollection-academic-fields { grid-template-columns: 1fr; }
}
.recollection-sacraments {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}
.recollection-sacraments legend {
  padding: 0;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 3px;
}
.recollection-consent {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 13px;
  line-height: 1.5;
}
.recollection-consent input {
  width: 17px;
  height: 17px;
  flex-shrink: 0;
  accent-color: #07634c;
  margin: 2px 0 0;
}
.recollection-consent + .recollection-consent {
  margin-top: -8px;
}
.recollection-form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-top: 20px;
}
.recollection-form-footer > .primary {
  min-width: 200px;
}
.recollection-next-note {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 16px;
  background: #eaf3ff;
  border-radius: 9px;
  flex: 1;
  min-width: 0;
  color: #406084;
}
.recollection-next-note > .icon {
  width: 30px;
  height: 30px;
  color: #2884d0;
  margin-top: 1px;
}
.recollection-next-note strong {
  color: #164c7e;
  font-size: 16px;
}
.recollection-next-note p {
  font-size: 13px;
  line-height: 1.5;
  margin-top: 4px;
}
.recollection-local-note {
  margin-top: 12px;
  text-align: center;
  color: #778799;
  font-size: 11px;
  line-height: 1.5;
}
.recollection-receipt-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 0.76fr);
  gap: 20px;
}
.recollection-receipt-grid > div {
  min-width: 0;
}
.recollection-receipt-grid h4 {
  font-size: 14px;
  line-height: 1.4;
  margin: 0 0 5px;
  font-weight: 600;
}
.recollection-upload-help {
  font-size: 12px;
  line-height: 1.5;
  margin-bottom: 6px;
}
.recollection-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 103px;
  padding: 12px 8px;
  border: 1px dashed #aebed9;
  border-radius: 5px;
  background: #fafcff;
  text-align: center;
  cursor: pointer;
  color: #34496d;
  line-height: 1.5;
}
.recollection-drop > .icon {
  width: 29px;
  height: 29px;
  color: #005d4b;
}
.recollection-drop small {
  font-size: 11px;
}
.recollection-drop span {
  font-size: 12px;
}
.recollection-drop:hover,
.recollection-drop.is-dragging {
  background: #edf7f1;
  border-color: #0c785e;
}
#recollection-receipt:focus-visible + .recollection-drop {
  outline: 3px solid #54b9a0;
  outline-offset: 3px;
}
.recollection-empty-receipt {
  display: grid;
  place-items: center;
  min-height: 96px;
  padding: 12px;
  color: #7c899e;
  border: 1px solid #e1e7ee;
  border-radius: 5px;
  background: #f6f8fa;
  text-align: center;
  font-size: 13px;
}
.recollection-receipt-thumb {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
  overflow: hidden;
  width: 100%;
  height: 96px;
  padding: 0;
  background: #f3f6f7;
  border: 1px solid #dce4e6;
  border-radius: 5px;
  color: #466170;
}
.recollection-receipt-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.recollection-preview-eye {
  position: absolute;
  bottom: 5px;
  right: 5px;
  border-radius: 50%;
  padding: 4px;
  width: 28px !important;
  height: 28px !important;
  background: #fff;
  color: #163a48;
}
.recollection-filename {
  font-size: 11px;
  margin: 5px 0;
  color: #657489;
  overflow-wrap: anywhere;
}
.recollection-receipt-actions {
  display: flex;
  gap: 10px;
  margin-top: 5px;
}
#recollection-root .recollection-receipt-actions button {
  flex: 1;
  min-width: 0;
  padding: 6px;
  min-height: 35px;
  font-size: 12px;
  gap: 6px;
  color: #005d45;
  border-color: #66a38f;
  background: white;
}
#recollection-root .recollection-receipt-actions #receipt-remove {
  color: #d02635;
  border-color: #e5747d;
}
.recollection-receipt-actions .icon {
  width: 16px;
  height: 16px;
}
.recollection-receipt-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 25px;
  background: #eef2f5;
  color: #657487;
  font-size: 12px;
  line-height: 1.4;
}
.recollection-receipt-status.has-receipt {
  color: #8a6200;
  background: #fff3ce;
}
.recollection-receipt-status.has-receipt:before {
  content: "";
  width: 8px;
  height: 8px;
  flex-shrink: 0;
  border-radius: 50%;
  background: #d99d0c;
}
.recollection-receipt-dialog {
  width: calc(100% - 32px);
  max-width: 850px;
  padding: 20px;
  max-height: calc(100dvh - 32px);
}
.recollection-dialog-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.recollection-dialog-heading h2 {
  font:
    700 24px/1.3 Georgia,
    serif;
  margin: 0;
}
#receipt-dialog-content img {
  display: block;
  max-width: 100%;
  max-height: calc(100dvh - 140px);
  object-fit: contain;
  margin: auto;
}
#receipt-dialog-content object {
  display: block;
  width: 100%;
  height: 65dvh;
  min-height: 150px;
}
@media (min-width: 1101px) and (max-width: 1280px) {
  .recollection-header {
    padding: 18px;
  }
  .recollection-header h1 {
    font-size: 28px;
  }
  .recollection-heading-row {
    gap: 12px;
  }
  #recollection-root .recollection-back {
    padding-inline: 12px;
    font-size: 12px;
  }
  .recollection-thirds {
    gap: 22px;
  }
}
@media (min-width: 901px) {
  .recollection-mode .dash-content:has(.recollection-step-2) {
    padding-top: 0;
  }
  .recollection-step-2 .recollection-header {
    padding: 12px 14px;
    margin-bottom: 10px;
  }
  .recollection-step-2 .recollection-header h1 {
    font-size: 29px;
  }
  .recollection-step-2 .recollection-steps {
    margin-top: 14px;
    max-width: 570px;
  }
  .recollection-step-2 .recollection-steps li {
    font-size: 12px;
  }
  .recollection-step-2 .recollection-steps li > span {
    width: 36px;
    height: 36px;
    margin-bottom: 3px;
    font-size: 21px;
  }
  .recollection-step-2 .recollection-steps li:before {
    top: 17px;
  }
  .recollection-step-2 .recollection-section-heading {
    padding: 10px 12px;
    gap: 12px;
    margin-bottom: 9px;
  }
  .recollection-step-2 .recollection-section-heading h2 {
    font-size: 21px;
  }
  .recollection-step-2 .recollection-section-heading p {
    font-size: 13px;
    margin-top: 1px;
  }
  .recollection-step-2 .recollection-group {
    margin-bottom: 12px;
  }
  .recollection-step-2 .recollection-group h3 {
    padding: 6px 10px;
    font-size: 15px;
    gap: 12px;
  }
  .recollection-step-2 .recollection-group-body {
    padding: 8px 12px 10px;
    gap: 10px;
  }
  .recollection-step-2 .recollection-field label {
    font-size: 12px;
    margin-bottom: 3px;
  }
  .recollection-step-2 .recollection-field input,
  .recollection-step-2 .recollection-field select,
  .recollection-step-2 .recollection-field textarea {
    font-size: 13px;
    min-height: 33px;
    padding: 6px 10px;
  }
  .recollection-step-2 .recollection-field textarea {
    min-height: 52px;
  }
  .recollection-step-2 .recollection-consent {
    font-size: 12px;
  }
  .recollection-step-2 .recollection-consent + .recollection-consent {
    margin-top: -5px;
  }
  .recollection-step-2 .recollection-form-footer {
    margin-top: 10px;
  }
}
@media (max-width: 900px) {
  .recollection-heading-row {
    flex-wrap: wrap;
  }
  .recollection-header h1 {
    font-size: 29px;
  }
  .recollection-header {
    padding: 20px;
  }
  .recollection-thirds {
    gap: 16px;
  }
  .recollection-form-footer {
    flex-wrap: wrap;
    gap: 16px;
  }
  .recollection-next-note {
    flex-basis: 100%;
  }
  .recollection-form-footer > .primary {
    margin-left: auto;
  }
  .recollection-receipt-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .recollection-mode .dash-content {
    padding: 12px;
  }
  .recollection-header {
    padding: 20px 15px;
  }
  .recollection-header h1 {
    font-size: 27px;
    letter-spacing: -0.5px;
  }
  .recollection-header p {
    font-size: 14px;
  }
  .recollection-heading-row {
    gap: 14px;
  }
  .recollection-steps {
    margin-top: 25px;
    gap: 10px;
  }
  .recollection-steps li {
    font-size: 12px;
  }
  .recollection-steps li:before {
    width: calc(100% + 10px);
  }
  .recollection-form {
    padding: 10px;
  }
  .recollection-section-heading {
    gap: 10px;
    padding: 14px 10px;
  }
  .recollection-section-heading h2 {
    font-size: 21px;
  }
  .recollection-section-heading > .icon {
    width: 24px;
    height: 24px;
  }
  .recollection-section-heading p {
    font-size: 13px;
  }
  .recollection-group h3 {
    font-size: 14px;
    gap: 10px;
    padding: 10px;
  }
  .recollection-group-body {
    padding: 12px 10px;
    gap: 14px;
  }
  .recollection-grid,
  .recollection-personal-row,
  .recollection-course-row,
  .recollection-thirds,
  .recollection-receipt-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 14px;
  }
  .recollection-field input,
  .recollection-field select,
  .recollection-field textarea,
  .recollection-academic-field select {
    font-size: 16px;
    min-height: 44px;
  }
  .recollection-field textarea {
    min-height: 90px;
  }
  .recollection-sacraments legend {
    margin-bottom: 8px;
  }
  .recollection-consent {
    gap: 10px;
    font-size: 13px;
  }
  .recollection-consent input {
    width: 20px;
    height: 20px;
  }
  .recollection-receipt-thumb,
  .recollection-empty-receipt {
    height: 150px;
  }
  .recollection-form-footer {
    gap: 12px;
    margin-top: 12px;
  }
  #recollection-root .recollection-form-footer > button {
    flex: 1 1 100%;
    min-width: 0;
    margin: 0;
    min-height: 46px;
  }
  .recollection-next-note {
    padding: 14px 12px;
    gap: 10px;
  }
  #recollection-root .recollection-receipt-actions button {
    min-height: 44px;
  }
  .recollection-receipt-dialog {
    padding: 14px;
  }
}

/* 7.2.6 — schedule-change */
/* Schedule Change Request page, inside the existing student portal. */
.schedule-change-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}
.schedule-change-mode .dash-content {
  min-width: 0;
  max-width: 1600px;
  padding: 26px 24px 24px;
}
.schedule-change-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}
.schedule-change-mode .student-change-page {
  min-width: 0;
  max-width: 100%;
  color: #17354b;
}
.schedule-change-mode .schedule-change-heading {
  margin-bottom: 22px;
  padding-bottom: 20px;
  border-bottom: 1px solid #d7dde5;
}
.schedule-change-mode .schedule-change-heading h1 {
  margin: 0 0 8px;
  color: #123e47;
  font: 700 clamp(29px, 3vw, 39px)/1.2 Georgia, "Times New Roman", serif;
  letter-spacing: -0.7px;
}
.schedule-change-mode .schedule-change-heading p {
  max-width: 850px;
  margin: 0;
  color: #5c6e7c;
  font-size: 16px;
  line-height: 1.5;
}
.schedule-change-mode .schedule-change-heading .student-change-eyebrow {
  margin-bottom: 7px;
  color: var(--green);
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 1.6px;
}
.schedule-change-mode .student-change-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}
.schedule-change-mode .student-change-stat {
  min-width: 0;
  padding: 17px 18px;
  background: #ffffffeb;
  border: 1px solid #dfe7ed;
  border-radius: 12px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.schedule-change-mode .student-change-stat-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: #5c6e7c;
  font-size: 13px;
  line-height: 1.4;
}
.schedule-change-mode .student-change-stat-head .icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  padding: 7px;
  color: var(--green);
  background: #e7f3ef;
  border-radius: 9px;
}
.schedule-change-mode .student-change-stat-count {
  display: block;
  margin-top: 9px;
  color: #123e47;
  font-size: 28px;
  line-height: 1.2;
}
.schedule-change-mode .student-change-stat small {
  display: block;
  margin-top: 5px;
  color: #5c6e7c;
  font-size: 12px;
  line-height: 1.45;
}
.schedule-change-mode .schedule-change-panel {
  min-width: 0;
  max-width: 100%;
  margin-bottom: 20px;
  padding: 0;
  overflow: hidden;
  background: #ffffffeb;
  border: 1px solid #dfe7ed;
  border-radius: 12px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.schedule-change-mode .student-change-panel-head {
  padding: 19px 24px;
  border-bottom: 1px solid #dfe7ed;
}
.schedule-change-mode .student-change-panel-head h2 {
  margin: 0;
  color: #123e47;
  font-size: 19px;
  line-height: 1.35;
}
.schedule-change-mode .student-change-panel-head p {
  margin: 5px 0 0;
  color: #5c6e7c;
  font-size: 13px;
  line-height: 1.5;
}
.schedule-change-mode #cfo-change-form {
  padding: 22px 24px;
}
.schedule-change-mode .student-change-fields {
  display: grid;
  gap: 18px;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.schedule-change-mode .student-change-field,
.schedule-change-mode .student-change-tool {
  min-width: 0;
}
.schedule-change-mode .student-change-field label,
.schedule-change-mode .student-change-tool label {
  display: block;
  margin-bottom: 7px;
  color: #17354b;
  font-size: 14px;
  font-weight: 650;
  line-height: 1.4;
}
.schedule-change-mode .student-change-field :is(input, textarea),
.schedule-change-mode .student-change-tool :is(input, select) {
  display: block;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  min-height: 44px;
  padding: 11px 13px;
  color: #19344b;
  background: #fcfcff;
  border: 1px solid #c8d1e1;
  border-radius: 7px;
  font: inherit;
  font-size: 15px;
}
.schedule-change-mode .student-change-field input[readonly] {
  background: #f1f7fa;
  text-overflow: ellipsis;
}
.schedule-change-mode .student-change-field textarea {
  min-height: 134px;
  resize: vertical;
  line-height: 1.5;
}
.schedule-change-mode .student-change-field textarea::placeholder,
.schedule-change-mode .student-change-tool input::placeholder {
  color: #667a86;
  opacity: 1;
}
.schedule-change-mode .student-change-page :is(input, textarea, select):focus-visible {
  border-color: #27846b;
}
.schedule-change-mode .student-change-page :is(button, input, textarea, select, [tabindex]):focus-visible,
.student-change-dialog :is(button, [tabindex]):focus-visible {
  outline: 3px solid #27846b;
  outline-offset: 3px;
}
.schedule-change-mode .student-change-field textarea:focus-visible {
  box-shadow: 0 0 0 3px #27846b18;
}
.schedule-change-mode .student-change-field textarea[aria-invalid="true"] {
  border-color: #ac3232;
}
.schedule-change-mode .student-change-reason-meta {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 5px 16px;
  margin-top: 7px;
  color: #5c6e7c;
  font-size: 12px;
  line-height: 1.5;
}
.schedule-change-mode .student-change-reason-meta > :last-child {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.schedule-change-mode .student-change-callout,
.schedule-change-mode .student-change-demo-note {
  margin: 14px 0 18px;
  padding: 13px 15px;
  color: #29584e;
  background: #edf7f3;
  border: 1px solid #d1e5dc;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.6;
}
.schedule-change-mode .student-change-submit {
  width: 100%;
  min-height: 48px;
  font-size: 16px;
}
.schedule-change-mode .student-change-page button:disabled,
.student-change-dialog button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}
.schedule-change-mode .student-change-fields:disabled textarea {
  color: #5c6e7c;
  background: #f1f7fa;
  cursor: not-allowed;
}
.schedule-change-mode .student-change-tools {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(150px, 0.7fr) auto;
  align-items: end;
  gap: 14px;
  padding: 17px 24px;
  border-bottom: 1px solid #dfe7ed;
}
.schedule-change-mode .student-change-tools button,
.schedule-change-mode .student-change-actions button,
.student-change-dialog-actions button {
  min-height: 44px;
  padding: 9px 13px;
  font-size: 13px;
}
.schedule-change-mode .student-change-table-wrap {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
}
.schedule-change-mode .student-change-table-wrap:focus-visible {
  outline-offset: -3px;
}
.schedule-change-mode .student-change-table {
  width: 100%;
  min-width: 1000px;
  margin: 0;
  border-collapse: collapse;
  table-layout: fixed;
}
.schedule-change-mode .student-change-table :is(th, td) {
  padding: 15px 17px;
  border-bottom: 1px solid #e7edf2;
  font-size: 13px;
  line-height: 1.5;
  text-align: left;
  vertical-align: top;
  overflow-wrap: anywhere;
}
.schedule-change-mode .student-change-table th {
  color: #5c6e7c;
  background: #f5f8fb;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.schedule-change-mode .student-change-table th:nth-child(1) { width: 16%; }
.schedule-change-mode .student-change-table th:nth-child(2) { width: 17%; }
.schedule-change-mode .student-change-table th:nth-child(3) { width: 19%; }
.schedule-change-mode .student-change-table th:nth-child(4) { width: 15%; }
.schedule-change-mode .student-change-table th:nth-child(5) { width: 21%; }
.schedule-change-mode .student-change-table th:nth-child(6) { width: 12%; }
.schedule-change-mode .student-change-table tbody tr:hover {
  background: #f9fcfb;
}
.schedule-change-mode .student-change-subtext {
  margin-top: 5px;
  color: #5c6e7c;
  font-size: 12px;
  line-height: 1.5;
}
.schedule-change-mode .student-change-reason {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
  white-space: pre-wrap;
}
.schedule-change-mode .student-change-actions,
.student-change-dialog-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
:is(.schedule-change-mode, .student-change-dialog) .student-change-status {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  color: #506675;
  background: #edf1f5;
  border-radius: 24px;
  font-size: 11px;
  font-weight: 750;
  line-height: 1.5;
  white-space: nowrap;
}
:is(.schedule-change-mode, .student-change-dialog) .student-change-status[data-status="Pending"] {
  color: #806000;
  background: #fff1c7;
}
:is(.schedule-change-mode, .student-change-dialog) .student-change-status[data-status="Approved"] {
  color: #005838;
  background: #dff2e9;
}
:is(.schedule-change-mode, .student-change-dialog) .student-change-status[data-status="Not Approved"] {
  color: #973c3c;
  background: #fff0ef;
}
.schedule-change-mode .student-change-empty {
  padding: 44px 20px;
  color: #5c6e7c;
  text-align: center;
}
.schedule-change-mode .student-change-empty > .icon {
  display: block;
  width: 36px;
  height: 36px;
  margin: 0 auto 12px;
  color: var(--green);
}
.schedule-change-mode .student-change-empty strong {
  display: block;
  color: #17354b;
}
.schedule-change-mode .student-change-empty p {
  margin: 7px 0 0;
  font-size: 13px;
  line-height: 1.5;
}
.schedule-change-mode .student-change-footer {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px 20px;
  padding: 14px 24px;
  color: #5c6e7c;
  border-top: 1px solid #dfe7ed;
  font-size: 12px;
  line-height: 1.5;
}
.student-change-dialog {
  width: min(720px, calc(100% - 32px));
  max-width: 720px;
  max-height: calc(100dvh - 32px);
  padding: 0;
  overflow: auto;
  color: #17354b;
  background: #fff;
  border: 1px solid #dfe7ed;
  border-radius: 14px;
}
.student-change-dialog::backdrop {
  background: #002f2abb;
  backdrop-filter: blur(4px);
}
.student-change-dialog-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 24px;
  border-bottom: 1px solid #dfe7ed;
}
.student-change-dialog-head h2 {
  margin: 0;
  font-size: 22px;
  line-height: 1.35;
}
.student-change-dialog-head button {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  padding: 8px;
  color: var(--green);
  background: #edf7f3;
  border-radius: 8px;
}
.student-change-dialog-head button .icon {
  width: 22px;
  height: 22px;
}
.student-change-details {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin: 0;
  padding: 22px 24px;
}
.student-change-details > div {
  min-width: 0;
  padding: 13px;
  background: #f1f7fa;
  border-radius: 8px;
}
.student-change-details > .full {
  grid-column: 1 / -1;
}
.student-change-details dt {
  margin-bottom: 6px;
  color: #5c6e7c;
  font-size: 12px;
}
.student-change-details dd {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
.student-change-dialog > p {
  margin: 0;
  padding: 20px 24px;
  font-size: 15px;
}
.student-change-dialog-actions {
  justify-content: flex-end;
  padding: 16px 24px;
  border-top: 1px solid #dfe7ed;
}
@media (max-width: 1100px) {
  .schedule-change-mode .student-change-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 760px) {
  .schedule-change-mode .dash-content {
    padding: 22px 14px 16px;
  }
  .schedule-change-mode .schedule-change-heading {
    margin-bottom: 18px;
    padding-bottom: 16px;
  }
  .schedule-change-mode .schedule-change-heading p {
    font-size: 15px;
  }
  .schedule-change-mode .schedule-change-heading .student-change-eyebrow {
    font-size: 11px;
  }
  .schedule-change-mode .student-change-stats {
    gap: 10px;
  }
  .schedule-change-mode .student-change-stat {
    padding: 14px;
  }
  .schedule-change-mode .student-change-stat-count {
    font-size: 25px;
  }
  .schedule-change-mode .student-change-panel-head,
  .schedule-change-mode #cfo-change-form {
    padding: 17px 16px;
  }
  .schedule-change-mode .student-change-tools {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
    padding: 16px;
  }
  .schedule-change-mode .student-change-tools button {
    width: 100%;
  }
  .schedule-change-mode .student-change-field :is(input, textarea),
  .schedule-change-mode .student-change-tool :is(input, select) {
    font-size: 16px;
  }
  .schedule-change-mode .student-change-footer {
    padding: 14px 16px;
  }
  .student-change-dialog-head,
  .student-change-dialog-actions,
  .student-change-dialog > p {
    padding: 17px 16px;
  }
  .student-change-details {
    grid-template-columns: minmax(0, 1fr);
    padding: 18px 16px;
  }
}

/* 7.2.7 — history */
.history-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}

@media (max-width: 520px) {
  .dash-notification-panel { right: -58px; }
}
.history-mode .dash-content {
  max-width: 1600px;
  padding: 26px 24px;
}
.history-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}

@media (max-width: 1100px) {
  .history-mode .dash-content {
    padding: 24px 20px;
  }
  
  
  
  
}
@media (max-width: 600px) {
  .history-mode .dash-content {
    padding: 22px 14px;
  }
  
  
  
  
  
}

/* 7.2.8 — certificate */
.certificate-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}
.certificate-mode .dash-content {
  max-width: 1600px;
  padding: 26px 24px;
}
.certificate-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}
.certificate-heading {
  margin-bottom: 27px;
}
.certificate-heading h1 {
  font:
    700 39px/1.2 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #123e47;
  margin: 0;
}
.certificate-heading p {
  font-size: 18px;
  line-height: 1.5;
  color: #7a86a3;
  margin: 4px 0 0;
}
.certificate-grid {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(290px, 1fr);
  gap: 16px;
  align-items: start;
}
.certificate-card {
  min-width: 0;
  padding: 21px;
  background: #ffffffeb;
  border: 1px solid #e8edf3;
  border-radius: 10px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.certificate-card h2 {
  display: flex;
  align-items: center;
  gap: 13px;
  margin: 0 0 18px;
  font:
    700 21px/1.3 Georgia,
    serif;
  color: #164b42;
}
.certificate-card h2 > .icon {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
}
.certificate-art {
  display: block;
  width: 100%;
  height: auto;
  aspect-ratio: 920/700;
  background: #f8faf8;
  border: 6px solid #f3f6f3;
}
.certificate-details h2 {
  padding-bottom: 17px;
  border-bottom: 1px solid #e7edf2;
  margin-bottom: 4px;
}
.certificate-details dl {
  margin: 0;
  color: #3b5271;
}
.certificate-detail {
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  gap: 12px;
  padding: 16px 0;
  border-bottom: 1px solid #e9eef3;
  font-size: 15px;
  line-height: 1.5;
}
.certificate-detail dt {
  font-weight: 600;
  min-width: 0;
}
.certificate-detail dd {
  margin: 0;
  min-width: 0;
  overflow-wrap: anywhere;
}
.certificate-detail:last-child {
  border: 0;
  padding-bottom: 28px;
}
.certificate-status {
  padding: 21px 0;
  border-top: 1px solid #e7edf2;
  border-bottom: 1px solid #e7edf2;
  margin-bottom: 20px;
}
.certificate-status p {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 600;
  color: #3b5271;
}
.certificate-available {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border-radius: 30px;
  padding: 9px 18px;
  background: #cdecd9;
  color: #116b43;
  font-size: 15px;
  font-weight: 600;
}
.certificate-available .icon {
  width: 23px;
  height: 23px;
  padding: 4px;
  border-radius: 50%;
  background: #006443;
  color: white;
}
.certificate-download {
  width: 100%;
  min-height: 51px;
  gap: 10px;
  padding: 12px;
  font-size: 15px !important;
  white-space: normal;
}
.certificate-download .icon {
  width: 21px;
  height: 21px;
  flex-shrink: 0;
}
.certificate-note {
  font-size: 12px;
  color: #738491;
  margin: 14px 0 0;
}
@media (max-width: 1200px) {
  .certificate-grid {
    grid-template-columns: minmax(0, 1.6fr) minmax(270px, 1fr);
  }
  .certificate-card {
    padding: 16px;
  }
  .certificate-detail {
    font-size: 14px;
  }
}
@media (max-width: 800px) {
  .certificate-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .certificate-mode .dash-content {
    padding: 24px 20px;
  }
  .certificate-heading h1 {
    font-size: 34px;
  }
  .certificate-heading p {
    font-size: 16px;
  }
}
@media (max-width: 600px) {
  .certificate-mode .dash-content {
    padding: 22px 14px;
  }
  .certificate-heading {
    margin-bottom: 20px;
  }
  .certificate-heading h1 {
    font-size: 29px;
  }
  .certificate-heading p {
    font-size: 15px;
  }
  .certificate-card {
    padding: 12px;
  }
  .certificate-card h2 {
    font-size: 19px;
    gap: 10px;
  }
  .certificate-art {
    border-width: 3px;
  }
  .certificate-detail {
    font-size: 14px;
  }
  .certificate-download {
    font-size: 15px !important;
  }
}

/* 7.2.9 — my-profile */
.my-profile-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}
.my-profile-mode .dash-content {
  max-width: 1600px;
  padding: 26px 24px;
}
.my-profile-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}

@media (max-width: 1100px) {
  .my-profile-mode .dash-content {
    padding: 24px 20px;
  }
  
  
}
@media (max-width: 600px) {
  .my-profile-mode .dash-content {
    padding: 22px 14px;
  }
  
  
  
  
  
  
  
  
}

/* The dashboard image URL is initialized from section 7.1.3 by the script. */
:root {
  --dashboard-reference: none;
}
  </style>
  <link rel="stylesheet" href="/assets/student/student-security.css">
  <link rel="stylesheet" href="{{ asset('assets/student/student-certificates.css') }}?v={{ filemtime(public_path('assets/student/student-certificates.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/student/student-pages.css') }}?v={{ filemtime(public_path('assets/student/student-pages.css')) }}">
@include('partials.cfo-usability')
  <link rel="stylesheet" media="screen" href="{{ asset('admin-typography.css') }}?v={{ filemtime(public_path('admin-typography.css')) }}">
  <link rel="stylesheet" media="screen" href="{{ asset('cfo-search-inputs.css') }}?v={{ filemtime(public_path('cfo-search-inputs.css')) }}">
<link rel="stylesheet" media="screen" href="{{ asset('admin-filter-dropdowns.css') }}?v={{ filemtime(public_path('admin-filter-dropdowns.css')) }}">
<script src="{{ asset('admin-filter-dropdowns.js') }}?v={{ filemtime(public_path('admin-filter-dropdowns.js')) }}" defer></script>
@include('partials.design-system')
</head>
<body>
  <!-- ==================== 7.1 HTML / STRUCTURE ==================== -->
  <!-- 7.1.1 Shared page mount, notices and accessible logout dialog -->
<div class="campus campus-left" aria-hidden="true"></div>
<div class="campus campus-right" aria-hidden="true"></div>
<main id="app"></main>
<dialog id="notice">
  <div class="dialog-icon" data-icon="info"></div>
  <h2 id="notice-title"></h2>
  <p id="notice-text"></p>
  <button class="primary" id="notice-close" type="button">Got it</button>
</dialog>
<dialog id="logoutOverlay" aria-labelledby="logoutTitle" aria-describedby="logoutDescription">
  <div class="logout-modal-content">
    <button type="button" id="closeLogout" aria-label="Close logout confirmation">×</button>
    <h2 id="logoutTitle">Confirm Logout</h2>
    <p id="logoutDescription">
      Are you sure you want to log out? You will return to the login page.
    </p>
    <div class="logout-modal-actions">
      <button type="button" id="cancelLogout" autofocus>Cancel</button
      ><button type="button" id="confirmLogout" class="primary">Logout</button>
    </div>
  </div>
</dialog>

  <noscript>This student panel needs JavaScript enabled to display its screens.</noscript>

  <!-- 7.1.2 Screen and reusable field templates.
       Edit markup here; edit data, validation and event handlers in section 7.3.
       Template comment wrappers keep partial table/attribute markup intact.
       Do not add a comment terminator inside a template's markup.
  -->

    <!-- icon: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-icon-1"><!--<svg class="icon @{{0}}" viewBox="0 0 24 24" aria-hidden="true"><path d="@{{1}}"/></svg>--></template>

    <!-- field: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-field-1"><!--<button type="button" class="reveal" aria-label="Show @{{0}}" data-reveal="@{{1}}">@{{2}}</button>--></template>

    <!-- field: 12 dynamic value(s); used by studentTemplate(). -->
    <template id="html-field-2"><!--<div class="field"><label for="@{{0}}">@{{1}}</label><div class="input-wrap @{{2}}">@{{3}}<input id="@{{4}}" name="@{{5}}" type="@{{6}}" placeholder="@{{7}}" value="@{{8}}" @{{9}} @{{10}}>@{{11}}</div></div>--></template>

    <!-- roleSelect: 0 dynamic value(s); used by studentTemplate(). -->
    

    <!-- roleSelect: 0 dynamic value(s); used by studentTemplate(). -->
    

    <!-- roleSelect: 10 dynamic value(s); used by studentTemplate(). -->
    

    <!-- banner: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-banner-1"><!--<div class="banner">@{{0}}<div><strong>@{{1}}</strong><p>@{{2}}</p></div></div>--></template>

    <!-- login: 9 dynamic value(s); used by studentTemplate(). -->
    

    <!-- reminder: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-reminder-1"><!--After completing the registration, your account will be subject for approval.<br>Visit the Christian Formation Office in person for verification and approval before you can log in to the system.--></template>

    <!-- reminder: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-reminder-2"><!--<aside class="reminder @{{0}}">@{{1}}<div><strong>Important Reminder</strong>@{{2}}</div></aside>--></template>

    <!-- actions: 5 dynamic value(s); used by studentTemplate(). -->
    <template id="html-actions-1"><!--<p class="error" id="form-error" role="alert"></p><div class="form-actions"><button class="outline" type="button" id="previous">@{{0}}@{{1}}</button><button class="primary" type="submit">@{{2}}@{{3}}@{{4}}</button></div>--></template>

    <!-- detail: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-detail-1"><!--<div class="detail-row"><dt>@{{0}}</dt><dd>@{{1}}</dd></div>--></template>

    <!-- registration: 9 dynamic value(s); used by studentTemplate(). -->
    

    <!-- registration: 2 dynamic value(s); used by studentTemplate(). -->
    

    <!-- registration: 6 dynamic value(s); used by studentTemplate(). -->
    

    <!-- registration: 12 dynamic value(s); used by studentTemplate(). -->
    

    <!-- registration: 4 dynamic value(s); used by studentTemplate(). -->
    

    <!-- registration: 3 dynamic value(s); used by studentTemplate(). -->
    

    <!-- resetActions: 4 dynamic value(s); used by studentTemplate(). -->
    

    <!-- resetCredential: 9 dynamic value(s); used by studentTemplate(). -->
    

    <!-- labels: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-labels-1"><!--Enter Temporary<br>Password--></template>

    <!-- labels: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-labels-2"><!--Create New<br>Password--></template>

    <!-- forgot: 4 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 5 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 2 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 6 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 1 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 1 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 4 dynamic value(s); used by studentTemplate(). -->
    

    <!-- forgot: 5 dynamic value(s); used by studentTemplate(). -->
    

    <!-- cfoForgotPassword: 5 dynamic value(s); used by studentTemplate(). -->
    

    <!-- dashboard: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-1"><!--<a href="#dashboard" @{{0}}>@{{1}}<span>@{{2}}</span></a>--></template>

    <!-- dashboard: 4 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-2"><!--<button type="button" data-dashboard-action="@{{0}}" @{{1}}>@{{2}}<span>@{{3}}</span></button>--></template>

    <!-- dashboard: 6 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-3"><!--<article class="dash-status dash-@{{0}}">@{{1}}<h2>@{{2}}</h2><p>@{{3}}</p><span class="dash-status-mark @{{4}}">@{{5}}</span></article>--></template>

    <!-- dashboard: 4 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-4"><!--<button type="button" class="dash-@{{0}}" data-dashboard-action="@{{1}}">@{{2}}<span>@{{3}}</span></button>--></template>

    <template id="html-dashboard-registration-notice"><!--
      <section id="dashboard-registration-notice" class="dash-panel" aria-labelledby="dashboard-registration-notice-title">
        <div class="dash-registration-notice-copy">
          <div class="dash-registration-notice-labels"><span class="badge">Absent</span><span class="dash-registration-notice-eyebrow">Attendance update</span></div>
          <h2 id="dashboard-registration-notice-title">Your registration is open again</h2>
          <p>Review your details and submit your registration again.</p>
          <p class="dash-registration-notice-history">Your missed attempt will remain in History.</p>
        </div>
        <div class="form-actions"><a class="primary" href="#recollection-registration">Fill out registration again<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg></a></div>
      </section>
    --></template>

    <!-- dashboard: 15 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-5"><!--
        <div class="dash-welcome"><div><p class="dash-greeting">Good day,</p><h1 id="dashboard-title">Juan Dela Cruz!</h1><p class="dash-subtitle">Welcome to your Recollection Dashboard</p></div><blockquote>“In faith, in community,<br>towards a better you.”<cite>— Christian Formation Office</cite></blockquote></div>
        <div class="dash-status-grid" aria-label="Student status">@{{0}}</div>
        <div class="dash-columns"><div class="dash-primary-column">
          <section class="dash-panel dash-upcoming" aria-labelledby="upcoming-title"><h2 id="upcoming-title">@{{1}}Upcoming Recollection</h2>
            <div class="dash-event-grid"><figure class="dash-chapel"><div class="dash-chapel-image"><img data-student-image="dashboard-reference" alt="Altar and cross inside the university chapel"></div><figcaption><q>Be still and know that I am God.</q><span>Psalm 46:10</span></figcaption></figure>
              <div class="dash-event-info"><dl><div>@{{2}}<dt>Date</dt><dd>September 18, 2026</dd></div><div>@{{3}}<dt>Time</dt><dd>8:00 AM – 4:00 PM</dd></div><div>@{{4}}<dt>Venue</dt><dd>Christian Formation Office<br>Recollection Hall</dd></div><div>@{{5}}<dt>Status</dt><dd><span class="dash-scheduled">Scheduled</span></dd></div></dl><button class="primary dash-view-schedule" type="button" data-dashboard-action="schedule">@{{6}}View Schedule @{{7}}</button></div>
            </div>
          </section>
          <aside class="dash-reflection"><span class="dash-leaf">@{{8}}</span><div><p>“A meaningful recollection is a step towards a better you.”</p><span>Christian Formation Office</span></div>@{{9}}</aside>
        </div><div class="dash-secondary-column">
          <section class="dash-panel dash-announcements" aria-labelledby="announcements-title"><div class="dash-panel-heading"><h2 id="announcements-title">@{{10}}Announcements</h2><button type="button" data-dashboard-action="announcements">View All</button></div>
            <article class="dash-announcement dash-green">@{{11}}<div><h3>Recollection 2026</h3><p>Registration is now open. Please submit your requirements on or before the deadline.</p><time datetime="2026-08-25">August 25, 2026</time></div></article>
            <article class="dash-announcement dash-blue">@{{12}}<div><h3>Bring Your Open Heart</h3><p>Let us prepare for a meaningful recollection experience.</p><time datetime="2026-08-20">August 20, 2026</time></div></article>
          </section>
          <section class="dash-panel dash-quick" aria-labelledby="quick-title"><h2 id="quick-title">@{{13}}Quick Actions</h2><div class="dash-quick-grid">@{{14}}</div></section>
        </div></div>
        <p class="dash-preview-note">UI preview · Sample student data</p>
        --></template>

    <!-- dashboard: 10 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-6"><!--<div class="dash-shell">
    <aside class="dash-sidebar" id="dashboard-navigation" aria-label="Student navigation">
      <button type="button" class="dash-sidebar-close dash-icon-button" aria-label="Close navigation">@{{0}}</button>
      <a class="dash-brand" href="#dashboard" aria-label="Christian Formation Office Recollection dashboard">
        <span class="dash-seal" role="img" aria-label="Christian Formation Office seal"></span>
        <span class="dash-university">Christian<br><strong>Formation Office</strong></span><span class="dash-brand-subtitle">Recollection</span>
      </a>
      <nav class="dash-nav" aria-label="Main navigation">@{{1}}</nav>
      <button type="button" class="dash-logout" id="openLogout" data-dashboard-action="logout">@{{2}}<span>Logout</span></button>
      <p class="dash-sidebar-quote">A Journey of Faith<br>A Brighter Tomorrow</p>
    </aside>
    <button type="button" class="dash-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
    <div class="dash-main-pane">
      <header class="dash-topbar">
        <button class="dash-icon-button" id="dashboard-menu" type="button" aria-controls="dashboard-navigation" aria-expanded="true" aria-label="Toggle navigation">@{{3}}</button>
        <div class="dash-user-tools">
          <div class="dash-notification-area">
            <button class="dash-icon-button dash-notifications" id="dashboard-notifications" type="button" data-dashboard-action="notifications" aria-controls="student-notification-panel" aria-expanded="false" aria-label="Notifications">@{{4}}<span id="student-notification-count" hidden></span></button>
            <section class="dash-notification-panel" id="student-notification-panel" aria-label="Student notifications" hidden>
              <header class="dash-notification-heading"><h2>Notifications</h2><span>New items appear here</span></header>
              <div class="dash-notification-list" id="student-notification-list" aria-live="polite"></div>
            </section>
          </div>
          <div class="dash-profile">
            <button type="button" class="dash-profile-button" id="dashboard-profile" aria-expanded="false" aria-controls="dashboard-profile-menu"><span class="dash-avatar">JD</span><span class="dash-profile-name"><strong>Juan Dela Cruz</strong><span>Student</span></span>@{{5}}</button>
            <div class="dash-profile-menu" id="dashboard-profile-menu" hidden><button type="button" data-dashboard-action="profile">@{{6}}My Profile</button><button type="button" data-dashboard-action="logout">@{{7}}Logout</button></div>
          </div>
        </div>
      </header>
      <section class="dash-content" aria-labelledby="@{{8}}">
        @{{9}}
      </section>
    </div>
  </div>--></template>

    <!-- dashboard: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-dashboard-7"><!--<p>No announcements have been published.</p>--></template>

    <!-- recollectionRegistration: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectionregistration-1"><!--<div id="recollection-root"></div>--></template>

    <!-- recollectionField: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectionfield-1"><!--<option value="@{{0}}" @{{1}}>@{{2}}</option>--></template>

    <!-- recollectionField: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectionfield-2"><!--<select @{{0}}><option value="">@{{1}}</option>@{{2}}</select>--></template>

    <!-- recollectionField: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectionfield-3"><!--<textarea @{{0}} rows="2" placeholder="@{{1}}">@{{2}}</textarea>--></template>

    <!-- recollectionField: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectionfield-4"><!--<div class="recollection-field"><label for="@{{0}}">@{{1}}</label>@{{2}}</div>--></template>

    <!-- recollectionGroup: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-recollectiongroup-1"><!--<section class="recollection-group"><h3>@{{0}}@{{1}}</h3><div class="recollection-group-body">@{{2}}</div></section>--></template>

    <!-- renderRecollectionStep: 7 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-1"><!--
      <div class="recollection-grid recollection-personal-row">@{{0}}@{{1}}@{{2}}</div>
      <div class="recollection-grid">@{{3}}</div>
      @{{4}}
      <div class="recollection-grid">@{{5}}@{{6}}</div>
    --></template>

    <!-- renderRecollectionStep: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-2"><!--
      @{{0}}
      <div class="recollection-grid">@{{1}}@{{2}}</div>
    --></template>

    <!-- renderRecollectionStep: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-3"><!--
      @{{0}}
      <div class="recollection-grid">@{{1}}@{{2}}</div>
    --></template>

    <!-- renderRecollectionStep: 6 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-4"><!--
      <div class="recollection-grid">@{{0}}@{{1}}</div>
      <fieldset class="recollection-sacraments"><legend>Sacraments Received (put Yes or No)</legend><div class="recollection-grid recollection-thirds">@{{2}}@{{3}}@{{4}}</div></fieldset>
      @{{5}}
    --></template>

    <!-- renderRecollectionStep: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-5"><!--<div class="recollection-grid">@{{0}}@{{1}}</div>--></template>

    <!-- renderRecollectionStep: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-6"><!--<label class="recollection-consent"><input name="participation" type="checkbox" @{{0}} required><span>I understand that this recollection is a faith-based activity and I freely choose to participate with openness, respect, and confidentiality.</span></label><label class="recollection-consent"><input name="photos" type="checkbox" @{{1}} required><span>I understand that during the recollection photos will be taken for the purposes of the University.</span></label>--></template>

    <!-- renderRecollectionStep: 1 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-7"><!--<div class="recollection-receipt-grid">
        <div><h4>Payment Receipt</h4><p class="recollection-upload-help" id="receipt-help">Upload a clear picture of your official receipt.</p><input class="sr-only" type="file" id="recollection-receipt" accept="image/jpeg,image/png,application/pdf" aria-label="Payment receipt" aria-describedby="receipt-help receipt-error"><label class="recollection-drop" for="recollection-receipt">@{{0}}<span><strong>Click to upload</strong> or drag and drop</span><small>JPG, PNG, PDF (Max 5MB)</small></label><p id="receipt-error" class="error" role="alert"></p></div>
        <div class="recollection-preview-column"><h4>Receipt Preview</h4><div id="receipt-preview"></div></div>
        <div><h4>Status</h4><span class="recollection-receipt-status" id="receipt-status">No receipt selected</span></div>
      </div>--></template>

    <!-- renderRecollectionStep: 1 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-8"><!--<button type="button" class="outline recollection-back" data-recollection-back>@{{0}}Back to Section 1</button>--></template>

    <!-- renderRecollectionStep: 1 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-9"><!--<a href="#dashboard" class="outline recollection-back">@{{0}}Back to Dashboard</a>--></template>

    <!-- renderRecollectionStep: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-10"><!--<button type="button" class="outline" data-recollection-back>@{{0}}Previous Section</button><button class="primary" type="submit">@{{1}}Submit Registration</button>--></template>

    <!-- renderRecollectionStep: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-11"><!--<aside class="recollection-next-note">@{{0}}<div><strong>Next Step</strong><p>After completing this section, you will proceed to Section 2: Faith Background to Consent.</p></div></aside><button class="primary" type="submit">Next Section @{{1}}</button>--></template>

    <!-- renderRecollectionStep: 10 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-12"><!--<header class="recollection-header"><div class="recollection-heading-row"><div><h1 id="recollection-title" tabindex="-1">Student Recollection Registration</h1><p>@{{0}}</p></div>@{{1}}</div>
    <ol class="recollection-steps" aria-label="Recollection registration progress"><li @{{2}}><span>1</span>Personal Information<br>to Health and Safety</li><li @{{3}}><span>2</span>Faith Background to Consent</li></ol></header>
    <form id="recollection-form" class="recollection-form"><div class="recollection-section-heading">@{{4}}<div><h2>Section @{{5}}</h2><p>@{{6}}</p></div></div>
      @{{7}}
      <footer class="recollection-form-footer">@{{8}}</footer>
      <p class="recollection-local-note">UI preview only. Information and receipt files stay in this page and are not submitted.</p>
    </form>
    <dialog id="receipt-dialog" class="recollection-receipt-dialog" aria-labelledby="receipt-dialog-title"><div class="recollection-dialog-heading"><h2 id="receipt-dialog-title">Receipt Preview</h2><button type="button" class="dash-icon-button" aria-label="Close receipt preview">@{{9}}</button></div><div id="receipt-dialog-content"></div></dialog>--></template>

    <!-- renderRecollectionStep: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-13"><!--<div class="recollection-group"><label class="recollection-field">Annual recollection<select id="cfo-schedule" required>--></template>

    <!-- renderRecollectionStep: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-14"><!--</option>--></template>

    <!-- renderRecollectionStep: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-renderrecollectionstep-15"><!--</select></label></div>--></template>

    <!-- updateRecollectionReceipt: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-updaterecollectionreceipt-1"><!--<div class="recollection-empty-receipt">No receipt selected</div>--></template>

    <!-- updateRecollectionReceipt: 1 dynamic value(s); used by studentTemplate(). -->
    <template id="html-updaterecollectionreceipt-2"><!--@{{0}}<span>PDF receipt</span>--></template>

    <!-- updateRecollectionReceipt: 5 dynamic value(s); used by studentTemplate(). -->
    <template id="html-updaterecollectionreceipt-3"><!--<button type="button" class="recollection-receipt-thumb" aria-label="View receipt preview">@{{0}}@{{1}}</button><p class="recollection-filename">@{{2}}</p><div class="recollection-receipt-actions"><button type="button" class="outline" id="receipt-replace">@{{3}}Replace</button><button type="button" class="outline" id="receipt-remove">@{{4}}Remove</button></div>--></template>

    <!-- scheduleChangeRequest: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-schedulechangerequest-1"><!--<div id="schedule-change-root"></div>--></template>

    <!-- scheduleChangeRequest: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-schedulechangerequest-2"><!--<option value="@{{0}}">@{{1}}</option>--></template>

    <!-- scheduleChangeRequest: 6 dynamic value(s); used by studentTemplate(). -->
    <template id="html-schedulechangerequest-3"><!--
    <header class="schedule-change-heading">
      <h1 id="schedule-change-title">Schedule Change Request</h1>
      <p>Request a new schedule for your recollection.</p>
    </header>

    <form id="schedule-change-form" class="schedule-change-panel">
      <section class="schedule-change-current" aria-labelledby="current-schedule-title">
        <h2 id="current-schedule-title">Current Schedule</h2>
        <div class="schedule-change-current-card">
          @{{0}}
          <div>
            <strong>September 18, 2026</strong>
            <p>8:00 AM – 4:00 PM</p>
            <p>Recollection Hall</p>
          </div>
        </div>
      </section>

      <div class="schedule-change-field">
        <label for="requested-schedule">Requested Schedule</label>
        <div class="schedule-change-select">
          @{{1}}
          <select id="requested-schedule" name="requestedSchedule" required aria-describedby="schedule-change-note">
            <option value="" selected disabled>Select requested schedule</option>
            @{{2}}
          </select>
          @{{3}}
        </div>
      </div>

      <div class="schedule-change-field">
        <label for="schedule-change-reason">Reason for Change</label>
        <textarea id="schedule-change-reason" name="reason" rows="4" required placeholder="Enter your reason for change" aria-describedby="schedule-change-error"></textarea>
        <p id="schedule-change-error" class="error" role="alert"></p>
      </div>

      <button class="primary full schedule-change-submit" type="submit">
        @{{4}}Submit Request
      </button>
    </form>

    <section class="schedule-change-panel schedule-change-status" aria-labelledby="request-status-title">
      <h2 id="request-status-title">Request Status</h2>
      <div class="schedule-change-status-card">
        <span class="schedule-change-clock">@{{5}}</span>
        <strong>Pending</strong>
      </div>
    </section>
    <p class="schedule-change-note" id="schedule-change-note">UI preview only. Schedule options and status are sample data; no request is sent.</p>--></template>

    <!-- cfoScheduleChange: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-cfoschedulechange-1"><!--<option value="@{{0}}">@{{1}}</option>--></template>

    <!-- cfoScheduleChange: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-cfoschedulechange-2"><!--<p>@{{0}} · @{{1}} · @{{2}}</p>--></template>

    <!-- cfoScheduleChange: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-cfoschedulechange-3"><!--<p>No requests submitted.</p>--></template>

    <!-- cfoScheduleChange: 3 dynamic value(s); used by studentTemplate(). -->
    <template id="html-cfoschedulechange-4"><!--<header class="schedule-change-heading"><h1 id="change-title">Schedule Change Request</h1><p>Tell the CFO why you need to change your recollection day. If your request is approved, the CFO will assign your new schedule.</p></header><form id="cfo-change-form" class="my-profile-card"><div class="field"><label for="change-registration">Current registration</label><select id="change-registration" required>@{{0}}</select></div>@{{1}}<button type="submit" class="primary">Submit Request</button><p id="change-availability"></p></form><section class="my-profile-card"><h2>Your requests</h2>@{{2}}</section>--></template>

    <!-- updateTargets: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-updatetargets-1"><!--<option value="@{{0}}">@{{1}}</option>--></template>

    <!-- Student schedule changes: shared live and demo page inside the existing portal shell. -->
    <template id="html-student-change-page"><!--
    <div id="student-change-page" class="student-change-page">
      <header class="schedule-change-heading">
        <p class="student-change-eyebrow">RECOLLECTION</p>
        <h1 id="change-title">Schedule Change Request</h1>
        <p>Submit a request to the CFO Office. The admin will review your reason and assign a new schedule if approved.</p>
      </header>
      @{{5}}
      <section class="student-change-stats" aria-label="Your request summary">@{{0}}</section>
      <section class="schedule-change-panel student-change-submit-panel" aria-labelledby="change-submit-title">
        <header class="student-change-panel-head"><h2 id="change-submit-title">Submit a Schedule Change Request</h2><p>Your current assignment is read-only. The admin chooses the new schedule.</p></header>
        <form id="cfo-change-form">
          <div class="student-change-field"><label for="change-current-schedule">Current Schedule</label><input id="change-current-schedule" class="readonly" value="@{{1}}" readonly><p class="student-change-subtext">This schedule is assigned by the CFO Office.</p></div>
          <p id="change-availability" class="student-change-callout" role="status" aria-live="polite">@{{2}}</p>
          <fieldset id="student-change-fields" class="student-change-fields" @{{3}}>
            <div class="student-change-field"><label for="change-reason">Reason for Request</label><textarea id="change-reason" name="reason" rows="4" required minlength="10" maxlength="2000" placeholder="Explain why you need to request a schedule change…" aria-describedby="change-reason-help change-reason-count change-reason-error"></textarea><div class="student-change-reason-meta"><p id="change-reason-help" class="student-change-subtext">Please provide enough information for the admin to review your request. (10–1,000 characters)</p><span id="change-reason-count" aria-live="polite" aria-atomic="true">0 / 1,000</span></div><p id="change-reason-error" class="error" role="alert"></p></div>
            <p class="student-change-callout">Submitting this request does not change your schedule automatically. The CFO admin will review it and, if approved, assign you to another available published recollection schedule.</p>
            <button class="primary full student-change-submit" type="submit">Submit Request @{{4}}</button>
          </fieldset>
        </form>
      </section>
      <section class="schedule-change-panel student-change-requests" aria-labelledby="change-requests-title">
        <header class="student-change-panel-head"><h2 id="change-requests-title">Your Requests</h2><p>Track decisions, admin comments, and any new schedule assignment.</p></header>
        <div class="student-change-tools">
          <div class="student-change-tool"><label for="student-change-search">Search requests</label><input id="student-change-search" type="search" placeholder="Search reason, schedule, status or comment"></div>
          <div class="student-change-tool"><label for="student-change-status">Status</label><select id="student-change-status"><option value="">All statuses</option><option>Pending</option><option>Approved</option><option>Not Approved</option><option>Cancelled</option></select></div>
          <button id="student-change-clear" class="outline" type="button">Clear filters</button>
        </div>
        <div class="student-change-table-wrap" role="region" aria-label="Your schedule change requests" tabindex="0"><table class="student-change-table"><thead><tr><th scope="col">Request Date</th><th scope="col">Current Schedule</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">Admin Comment / Assigned Schedule</th><th scope="col">Actions</th></tr></thead><tbody id="student-change-body"></tbody></table></div>
        <div id="student-change-empty" class="student-change-empty" hidden><strong></strong><p></p></div>
        <footer class="student-change-footer"><span id="student-change-showing" role="status" aria-live="polite"></span><span>Christian Formation Office</span></footer>
      </section>
    </div>
    --></template>

    <!-- certificateArtwork: 1 dynamic value(s); used by studentTemplate(). -->
    <template id="html-certificateartwork-1"><!--<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 920 700" class="certificate-art" role="img" aria-labelledby="certificate-art-title certificate-art-desc">
    <title id="certificate-art-title">Certificate of Participation — Juan Dela Cruz</title>
    <desc id="certificate-art-desc">Sample certificate for participation in the Christian Formation Office recollection, September 18, 2026, Santiago City, Isabela.</desc>
    <svg width="920" height="700" viewBox="227 175 457 347" preserveAspectRatio="none" overflow="hidden">
      <image width="975" height="650" href="@{{0}}"/>
    </svg>
  </svg>--></template>

    <!-- studentCertificate: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-studentcertificate-1"><!--<header class="certificate-heading"><h1 id="certificate-title">Certificate</h1><p>Official certificate issuance is not configured. Contact the CFO for your certificate.</p></header>--></template>

    <!-- studentCertificate: 2 dynamic value(s); used by studentTemplate(). -->
    <template id="html-studentcertificate-2"><!--<div class="certificate-detail"><dt>@{{0}}</dt><dd>@{{1}}</dd></div>--></template>

    <!-- studentCertificate: 5 dynamic value(s); used by studentTemplate(). -->
    <template id="html-studentcertificate-3"><!--
    <header class="certificate-heading"><h1 id="certificate-title">Certificate</h1><p>View and download your recollection certificate.</p></header>
    <div class="certificate-grid">
      <section class="certificate-card" aria-labelledby="certificate-preview-title">
        <h2 id="certificate-preview-title">@{{0}}Certificate Preview</h2>
        @{{1}}
      </section>
      <section class="certificate-card certificate-details" aria-labelledby="certificate-details-title">
        <h2 id="certificate-details-title">@{{2}}Certificate Details</h2>
        <dl>@{{3}}</dl>
        <div class="certificate-status"><p>Certificate Status</p><span class="certificate-available">@{{4}}Available</span></div>
        <button type="button" id="certificate-download" class="primary certificate-download"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg>Download Certificate</button>
      </section>
    </div>
    <p class="certificate-note">UI preview · Sample certificate</p>--></template>

    <!-- svg: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-svg-1"><!--</svg>--></template>

    <!-- svg: 0 dynamic value(s); used by studentTemplate(). -->
    <template id="html-svg-2"><!--<text x="16" y="682" font-family="Arial,sans-serif" font-size="11" fill="#718278">SAMPLE · UI PREVIEW</text></svg>--></template>

  <!-- 7.1.3 Dashboard image resource shared by CSS and visible page images. -->
  <template id="embedded-student-assets">
    <img data-resource="dashboard-reference" src="{{ asset('assets/student/images/dashboard-reference.webp') }}" alt="">
  </template>

  <!-- ==================== 7.3 JAVASCRIPT / FUNCTIONALITY ==================== -->
  <script src="/assets/student/student-security.js"></script>
  <script src="{{ asset('assets/student/student-certificates.js') }}?v={{ filemtime(public_path('assets/student/student-certificates.js')) }}"></script>
  <script src="{{ asset('admin-history-documents.js') }}?v={{ filemtime(public_path('admin-history-documents.js')) }}"></script>
  <script src="{{ asset('assets/student/student-pages.js') }}?v={{ filemtime(public_path('assets/student/student-pages.js')) }}"></script>
  <script src="{{ asset('assets/student/student-receipts.js') }}?v={{ filemtime(public_path('assets/student/student-receipts.js')) }}"></script>
  <script>
// Templates in section 7.1 keep HTML separate from behavior. A comment node
// preserves attribute/row fragments exactly, without HTML parser rewriting.
// @{{0}}, @{{1}}, ... are positional values supplied by the calling function.
// User-entered values keep the original escapeHTML calls. No eval is used.
const templateCache = new Map();
function studentTemplate(id, values = []) {
  if (!templateCache.has(id)) {
    const template = document.getElementById(id);
    const comment = [...template.content.childNodes].find((node) => node.nodeType === Node.COMMENT_NODE);
    templateCache.set(id, comment.data);
  }
  return templateCache.get(id).replace(/\{\{(\d+)\}\}/g, (_, index) => String(values[Number(index)]));
}
// Share the extracted image URLs between CSS backgrounds and page images.
const embeddedImages = new Map();
document
  .querySelector("#embedded-student-assets")
  .content.querySelectorAll("img")
  .forEach((image) => {
    const url = image.getAttribute("src");
    embeddedImages.set(image.dataset.resource, url);
    document.documentElement.style.setProperty("--" + image.dataset.resource, 'url("' + url + '")');
  });
function hydrateStudentImages() {
  document.querySelectorAll("img[data-student-image]").forEach((img) => {
    const name = img.dataset.studentImage;
    img.src = embeddedImages.get(name);
    img.removeAttribute("data-student-image");
  });
}
new MutationObserver(hydrateStudentImages).observe(document.body, { childList: true, subtree: true });
// Opening the file directly uses the existing, explicitly labeled UI preview.
// Laravel can supply the same-origin session as inert metadata when serving it.
const backendMetadata = document.querySelector('meta[name="cfo-backend"]');
if (backendMetadata) window.CFO_BACKEND = JSON.parse(backendMetadata.content);

/* 7.3.1 — Icons */
// SVG icons shared by all screens.

const paths = {
  user: "M20 21v-2a6 6 0 0 0-12 0v2M14 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M3 18h5M5.5 15.5v5",
  person: "M5 21v-2a7 7 0 0 1 14 0v2ZM12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8",
  mail: "M3 5h18v14H3ZM3 6l9 7 9-7",
  lock: "M5 10h14v11H5ZM8 10V6a4 4 0 0 1 8 0v4M12 14v3",
  eye: "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6",
  right: "M5 12h14M13 6l6 6-6 6",
  left: "M19 12H5M11 6l-6 6 6 6",
  chevron: "M9 4l8 8-8 8",
  down: "M6 9l6 6 6-6",
  login: "M14 3h7v18h-7M3 12h13M10 6l6 6-6 6",
  check: "M4 12l5 5L20 6",
  close: "M5 5l14 14M19 5L5 19",
  info: "M12 17v-5M12 7v.1M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0",
  cap: "M2 8l10-5 10 5-10 5ZM5 10v7c4 4 10 4 14 0v-7M22 8v9",
  id: "M2 5h20v14H2ZM6 15v-1a2 2 0 0 1 4 0v1M8 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4M14 9h4M14 14h4",
  shield: "M12 2l9 4v6c0 5-5 9-9 11-4-2-9-6-9-11V6ZM7 12l4 4 6-7",
  edit: "M14 5l5 5M3 21l5-1L21 7l-5-5L3 15ZM3 21l1-5",
  file: "M14 2H5v20h15V8ZM14 2v6h6M8 12h8M8 17h8",
};

Object.assign(paths, {
  copy: "M9 3h11v14H9ZM6 7H3v14h11v-2",
  key: "M14 3a6 6 0 0 0-4 10L5 21H2v-4l5-7a6 6 0 0 0 7 3M15 6h.01",
  send: "M22 2L9 15M22 2l-7 20-6-7-7-6Z",
});

const icon = (name, cls = "") => studentTemplate("html-icon-1", [cls, paths[name] || paths.info]);

/* 7.3.2 — Shared fields and dialogs */
// Shared fields, notices, and password validation. Loaded before page scripts.

// Mount the shared UI shell before rendering a page.

const app = document.querySelector("#app");

const escapeHTML = (value) =>
  String(value).replace(
    /[&<>"']/g,
    (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
  );

function field(id, label, placeholder, type = "text", symbol = "person", value = "", required = true) {
  return studentTemplate("html-field-2", [
    id,
    label,
    type === "password" ? "password-input" : "",
    icon(symbol),
    id,
    id,
    type,
    placeholder,
    escapeHTML(value),
    required ? "required" : "",
    type === "password" ? 'autocomplete="new-password"' : type === "email" ? 'autocomplete="email"' : "",
    type === "password" ? studentTemplate("html-field-1", [label.toLowerCase(), id, icon("eye")]) : "",
  ]);
}


function showNotice(title, text) {
  document.querySelector("#notice-title").textContent = title;
  document.querySelector("#notice-text").textContent = text;
  document.querySelector(".dialog-icon").innerHTML = icon("info");
  document.querySelector("#notice").showModal();
}

document
  .querySelector("#notice-close")
  .addEventListener("click", () => document.querySelector("#notice").close());

function bindReveal() {
  document.querySelectorAll("[data-reveal]").forEach(
    (button) =>
      (button.onclick = () => {
        const input = document.getElementById(button.dataset.reveal);
        const showing = input.type === "password";
        input.type = showing ? "text" : "password";
        button.setAttribute(
          "aria-label",
          `${showing ? "Hide" : "Show"} ${document.querySelector(`label[for="${input.id}"]`).textContent.toLowerCase()}`,
        );
        button.setAttribute("aria-pressed", String(showing));
      }),
  );
}

function banner(symbol, title, text) {
  return studentTemplate("html-banner-1", [icon(symbol), title, text]);
}

const rules = [
  { text: "At least 8 characters", test: (v) => v.length >= 8 },
  { text: "At least one uppercase letter (A–Z)", test: (v) => /[A-Z]/.test(v) },
  { text: "At least one lowercase letter (a–z)", test: (v) => /[a-z]/.test(v) },
  { text: "At least one number (0–9)", test: (v) => /[0-9]/.test(v) },
  { text: "At least one special character (e.g. ! @ # $ % ^ & *)", test: (v) => /[^a-zA-Z0-9\s]/.test(v) },
];

// Shared logout confirmation, mounted once for every student screen.

const logoutOverlay = document.getElementById("logoutOverlay");
function openLogoutModal(returnFocusTo) {
  if (logoutOverlay.open) return;
  // Focus a visible trigger so the native dialog can restore focus on dismissal.
  returnFocusTo?.focus({ preventScroll: true });
  logoutOverlay.showModal();
  document.body.classList.add("logout-modal-open");
  document.getElementById("cancelLogout").focus();
}
function closeLogoutModal() {
  if (logoutOverlay.open) logoutOverlay.close();
  document.body.classList.remove("logout-modal-open");
}
document.getElementById("closeLogout").addEventListener("click", closeLogoutModal);
document.getElementById("cancelLogout").addEventListener("click", closeLogoutModal);
logoutOverlay.addEventListener("close", () => document.body.classList.remove("logout-modal-open"));
logoutOverlay.addEventListener("cancel", (event) => {
  event.preventDefault();
  closeLogoutModal();
});
let logoutBackdropPressed = false;
function outsideLogout(event) {
  const bounds = logoutOverlay.getBoundingClientRect();
  return (
    event.target === logoutOverlay &&
    (event.clientX < bounds.left ||
      event.clientX > bounds.right ||
      event.clientY < bounds.top ||
      event.clientY > bounds.bottom)
  );
}
logoutOverlay.addEventListener("pointerdown", (event) => {
  logoutBackdropPressed = outsideLogout(event);
});
logoutOverlay.addEventListener("click", (event) => {
  if (logoutBackdropPressed && outsideLogout(event)) closeLogoutModal();
  logoutBackdropPressed = false;
});
document.getElementById("confirmLogout").addEventListener("click", async () => {
  if (window.CFO_BACKEND) {
    try {
      await cfoRequest("/api/logout", "POST");
      location.href = "/login";
    } catch (error) {
      closeLogoutModal();
      showNotice("Logout failed", error.message);
    }
    return;
  }
  closeLogoutModal();
  // UI-only navigation; no server session or authentication service is connected.
  location.href = "/login";
});

// Same-origin Laravel sessions are enabled only by the Laravel page controller.
let cfoStudent = { schedules: [], registration_schedules: [], registrations: [], requests: [], notifications: [], announcements: [], notification_unviewed_count: 0 };
async function cfoRequest(url, method = "GET", data) {
  const multipart = data instanceof FormData;
  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-CSRF-TOKEN": window.CFO_BACKEND.csrf_token,
      ...(data && !multipart ? { "Content-Type": "application/json" } : {}),
    },
    ...(data ? { body: multipart ? data : JSON.stringify(data) } : {}),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401) location.href = "/login";
    const error = new Error(
      response.status === 419
        ? "Your session expired. Reload the page and try again."
        : Object.values(result.errors || {})
            .flat()
            .join("\n") ||
            result.message ||
            "The request could not be completed.",
    );
    error.fields = result.errors;
    throw error;
  }
  if (result.csrf_token) Object.assign(window.CFO_BACKEND, result);
  return result;
}
async function cfoSubmit(form, action, skipConfirmation = false, review = []) {
  if (!skipConfirmation) {
    let dialog = document.querySelector('#action-confirm');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.id = 'action-confirm';
      dialog.style.cssText = 'width:min(440px,calc(100% - 32px));padding:24px;border:1px solid #dce7e1;border-radius:14px;box-shadow:0 20px 60px #002c3540;color:#173b45';
      dialog.innerHTML = '<h2 id="action-confirm-title">Confirm before saving</h2><p id="action-confirm-text"></p><div id="action-confirm-details" style="max-height:45vh;overflow:auto;overflow-wrap:anywhere"></div><div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px"><button type="button" class="outline" id="action-confirm-cancel">Cancel</button><button type="button" class="primary" id="action-confirm-accept">Confirm</button></div>';
      document.body.append(dialog);
    }
    const label = form.querySelector('[type=submit]')?.textContent.trim() || 'save your changes';
    dialog.querySelector('#action-confirm-text').textContent = 'This will ' + label.toLowerCase() + ' to your CFO account. Please check the details before confirming.';
    const details = dialog.querySelector('#action-confirm-details');
    details.replaceChildren();
    for (const entry of review) {
      const row = document.createElement('section'); row.style.cssText='padding:10px 0;border-bottom:1px solid #dce7e1';
      const title = document.createElement('strong'); title.textContent=entry.label; row.append(title);
      const isEmail = /email/i.test(entry.label);
      if(entry.before !== undefined){const before=document.createElement('p');before.style.cssText='margin:4px 0;color:#63737a;white-space:pre-wrap';if(isEmail)before.innerHTML='Before: '+studentEmailDisplay(entry.before);else before.textContent='Before: '+entry.before;row.append(before);}
      const after=document.createElement('p');after.style.cssText='margin:4px 0;white-space:pre-wrap';if(isEmail)after.innerHTML=(entry.before !== undefined?'After: ':'')+studentEmailDisplay(entry.after);else after.textContent=(entry.before !== undefined?'After: ':'')+entry.after;row.append(after);details.append(row);
    }
    dialog.showModal();
    dialog.querySelector('#action-confirm-cancel').onclick = () => dialog.close();
    dialog.querySelector('#action-confirm-accept').onclick = () => { dialog.close(); cfoSubmit(form, action, true, review); };
    return;
  }
  const button = form.querySelector("[type=submit]");
  if (button?.disabled) return;
  if (button) button.disabled = true;
  try {
    await action();
  } catch (error) {
    window.CfoUsability?.formError(form, error);
    showNotice("Please check your request", error.message);
  } finally {
    if (button) button.disabled = false;
  }
}

// Consistent button contrast across the existing student and admin interfaces.

/* 7.3.3 — Login */
// Login screen and its UI-only actions.

/* 7.3.6 — Dashboard and navigation */
// Visual design and responsive layouts.

// Static student dashboard preview. No student data is fetched or submitted.
let dashboardCleanup = () => {};

// Keep the desktop preference across student page transitions for this session.
let dashboardSidebarCollapsed = false;
const studentNavigationRoutes = {
  dashboard: "#dashboard",
  registration: "#recollection-registration",
  change: "#schedule-change-request",
  history: "#recollection-history",
  certificate: "#certificate",
  profile: "#my-profile",
  announcements: "#announcements",
};

const studentNotificationTypes = {
  schedule_change_approved: { icon: "change", label: "Schedule change approved" },
  schedule_change_rejected: { icon: "change", label: "Schedule change not approved" },
  registration_approved: { icon: "file", label: "Registration approved" },
  certificate_ready: { icon: "award", label: "Certificate ready" },
  attendance_present: { icon: "calendar", label: "Attendance marked present" },
  attendance_absent: { icon: "calendar", label: "Attendance marked absent" },
  announcement: { icon: "announce", label: "New announcement" },
};

function relativeNotificationTime(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Recently";
  const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
  const units = [[60, "second"], [60, "minute"], [24, "hour"], [7, "day"], [4.35, "week"], [12, "month"], [Infinity, "year"]];
  let amount = seconds;
  for (const [limit, unit] of units) {
    if (amount < limit) return new Intl.RelativeTimeFormat(undefined, { numeric: "auto" }).format(-Math.floor(amount), unit);
    amount /= limit;
  }
  return "Recently";
}

function renderStudentNotifications() {
  const list = document.querySelector("#student-notification-list");
  if (!list) return;
  const rows = cfoStudent.notifications || [];
  list.innerHTML = rows.map((notification) => {
    const detail = studentNotificationTypes[notification.type] || { icon: "bell", label: "Notification" };
    const viewed = Boolean(notification.read_at);
    return `<a class="dash-notification-item ${viewed ? "" : "is-unviewed"}" href="${escapeHTML(notification.target || "#dashboard")}" aria-label="${escapeHTML(detail.label + ". " + notification.message)}"><span class="dash-notification-icon">${icon(detail.icon)}</span><span class="dash-notification-copy">${escapeHTML(notification.message)}<time datetime="${escapeHTML(notification.created_at || "")}">${escapeHTML(relativeNotificationTime(notification.created_at))}</time></span>${viewed ? "" : '<span class="dash-notification-unread" aria-label="Unviewed"></span>'}</a>`;
  }).join("") || '<p class="dash-notification-empty">You are all caught up.</p>';
}

const viewedStudentNotificationIds = new Set();
let studentNotificationViewBusy = false;
function updateStudentNotificationBell() {
  cfoStudent.notifications = (cfoStudent.notifications || []).map(n => viewedStudentNotificationIds.has(Number(n.id)) && !n.read_at ? {...n, read_at: new Date().toISOString()} : n);
  const count = cfoStudent.notifications.filter(n => !n.read_at).length;
  cfoStudent.notification_unviewed_count = count;
  const button = document.querySelector('#dashboard-notifications');
  const badge = document.querySelector('#student-notification-count');
  if (!button || !badge) return;
  badge.textContent = String(count);
  badge.hidden = count === 0;
  button.setAttribute('aria-label', count ? 'Notifications, ' + count + ' unviewed' : 'Notifications');
  renderStudentNotifications();
}

async function markStudentNotificationsViewed() {
  if (studentNotificationViewBusy) return;
  // Acknowledge only the list actually displayed, never notifications arriving later.
  const ids = (cfoStudent.notifications || []).filter(n => !n.read_at).map(n => Number(n.id));
  if (!ids.length) return;
  studentNotificationViewBusy = true;
  try {
    for (let offset = 0; offset < ids.length; offset += 500) {
      const batch = ids.slice(offset, offset + 500);
      await cfoRequest('/api/student/notifications/viewed', 'POST', {ids: batch});
      batch.forEach(id => viewedStudentNotificationIds.add(id));
      updateStudentNotificationBell();
    }
  } finally { studentNotificationViewBusy = false; }
}

function closeStudentNotificationPanel(returnFocus = false) {
  const panel = document.querySelector("#student-notification-panel");
  const button = document.querySelector("#dashboard-notifications");
  if (!panel || !button || panel.hidden) return;
  panel.hidden = true;
  button.setAttribute("aria-expanded", "false");
  if (returnFocus) button.focus();
}

function positionStudentNotificationPanel() {
  const panel=document.querySelector('#student-notification-panel');
  const button=document.querySelector('#dashboard-notifications');
  if(!panel||panel.hidden||!button)return;
  const rect=button.getBoundingClientRect(), width=Math.min(390,window.innerWidth-32);
  const top=Math.max(8,Math.min(rect.bottom+8,window.innerHeight-120));
  panel.style.position='fixed';panel.style.right='auto';panel.style.top=top+'px';
  panel.style.left=Math.max(16,Math.min(rect.right-width,window.innerWidth-width-16))+'px';
  panel.style.maxHeight=Math.max(100,Math.min(580,window.innerHeight-top-16))+'px';
}

async function toggleStudentNotificationPanel() {
  const panel = document.querySelector("#student-notification-panel");
  const button = document.querySelector("#dashboard-notifications");
  if (!panel || !button) return;
  if (!panel.hidden) {
    closeStudentNotificationPanel();
    return;
  }
  panel.hidden = false;
  positionStudentNotificationPanel();
  button.setAttribute("aria-expanded", "true");
  renderStudentNotifications();
  try { await markStudentNotificationsViewed(); }
  catch (error) { showNotice("Notifications could not be marked viewed", error.message); }
}


// History, View All Announcements and My Profile live in public/assets/student/student-pages.js (same pattern as the certificate page).
function studentPage(name, active, titleId) {
  dashboard({ active, titleId, content: CfoStudentPages.pageHtml(name, cfoStudent, window.CFO_BACKEND.user) });
  const cleanup = CfoStudentPages.mount(name, document.querySelector("[data-student-page]"), {
    state: cfoStudent, user: window.CFO_BACKEND.user, request: cfoRequest, submit: cfoSubmit, showNotice, refresh: studentMyProfile,
  });
  const previousCleanup = dashboardCleanup;
  dashboardCleanup = () => { cleanup(); previousCleanup(); };
}
function recollectionHistory() { studentPage("history", "history", "history-title"); }
function studentAnnouncements() { studentPage("announcements", "announcements", "announcements-title"); }
function studentMyProfile() { studentPage("profile", "profile", "my-profile-title"); }

function dashboard({ content = null, active = "dashboard", titleId = "recollection-title" } = {}) {
  document.body.classList.remove("registration-mode");
  Object.assign(paths, {
    home: "M3 10l9-8 9 8M5 9v12h5v-7h4v7h5V9",
    calendar: "M3 5h18v16H3ZM7 2v6M17 2v6M3 10h18M7 14h1M12 14h1M17 14h1M7 18h1M12 18h1M17 18h1",
    clock: "M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0M12 6v6l4 3",
    history: "M3 11a9 9 0 1 1 2 7M3 5v6h6M12 7v6l4 2",
    change: "M3 7h15M14 3l4 4-4 4M21 17H6M10 13l-4 4 4 4",
    award: "M18 8a6 6 0 1 0-12 0 6 6 0 0 0 12 0M8 13L5 22l5-2 2 2 2-2 5 2-3-9",
    settings:
      "M10 2h4l1 3 3 1 3-1 2 4-2 2v3l2 2-2 4-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-4 2-2v-3L1 9l2-4 3 1 3-1ZM16 12a4 4 0 1 0-8 0 4 4 0 0 0 8 0",
    menu: "M4 6h16M4 12h16M4 18h16",
    bell: "M5 17h14l-2-4V9a5 5 0 0 0-10 0v4ZM10 21h4M12 2v2",
    location: "M19 9c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 14 0ZM14 9a2 2 0 1 0-4 0 2 2 0 0 0 4 0",
    announce: "M3 9h5l12-5v16L8 15H3ZM8 15l2 6H6l-2-6M20 9h2v6h-2",
    lightning: "M13 2L4 14h7l-1 8 10-13h-8Z",
    leaf: "M20 3C9 1 3 8 6 16c8 6 16-1 14-13ZM4 22L16 8M9 17l-1-6M12 14h5",
    people:
      "M15 21v-2a6 6 0 0 0-12 0v2ZM9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 3a4 4 0 0 1 0 8M18 14a5 5 0 0 1 3 5v2",
    minus: "M5 12h14",
  });
  const navigation = [
    ["home", "Dashboard", "dashboard"],
    ["file", "Recollection Registration", "registration"],
    ["change", "Schedule Change Request", "change"],
    ["history", "Recollection History", "history"],
    ["announce", "View All Announcements", "announcements"],
    ["award", "Certificate", "certificate"],
    ["person", "My Profile", "profile"],
  ];
  const cards = [
    ["green", "person", "Account Status", "Approved", "check"],
    ["gold", "file", "Registration Status", "Pending", "clock"],
    ["blue", "calendar", "Recollection Schedule", "September 18, 2026", "calendar"],
    ["purple", "people", "Attendance", "Not Yet Recorded", "minus"],
    ["rose", "award", "Certificate", "Not Yet Available", "minus"],
  ];
  const quickActions = [
    ["green", "file", "Register Now", "registration"],
    ["blue", "calendar", "View Schedule", "schedule"],
    ["purple", "change", "Request Change", "change"],
    ["rose", "award", "View Certificate", "certificate"],
  ];

  app.innerHTML = studentTemplate("html-dashboard-6", [
    icon("close"),
    navigation
      .map(([symbol, label, action]) =>
        action === "dashboard"
          ? studentTemplate("html-dashboard-1", [
              active === action ? 'class="is-active" aria-current="page"' : "",
              icon(symbol),
              label,
            ])
          : studentTemplate("html-dashboard-2", [
              action,
              active === action ? 'class="is-active" aria-current="page"' : "",
              icon(symbol),
              label,
            ]),
      )
      .join(""),
    icon("login"),
    icon("menu"),
    icon("bell"),
    icon("down"),
    icon("person"),
    icon("login"),
    content === null ? "dashboard-title" : titleId,
    content ??
      studentTemplate("html-dashboard-5", [
        cards
          .map(([color, symbol, label, value, statusIcon]) =>
            studentTemplate("html-dashboard-3", [
              color,
              icon(symbol, "dash-status-icon"),
              label,
              value,
              statusIcon === "calendar" ? "is-calendar" : "",
              icon(statusIcon),
            ]),
          )
          .join(""),
        icon("calendar"),
        icon("calendar"),
        icon("clock"),
        icon("location"),
        icon("file"),
        icon("calendar"),
        icon("right"),
        icon("leaf"),
        icon("leaf", "dash-leaf-decoration"),
        icon("announce"),
        icon("info"),
        icon("info"),
        icon("lightning"),
        quickActions
          .map(([color, symbol, label, action]) =>
            studentTemplate("html-dashboard-4", [color, action, icon(symbol), label]),
          )
          .join(""),
      ]),
  ]);

  const shell = app.querySelector(".dash-shell");
  shell.querySelector(".dash-brand-subtitle").textContent = navigation.find(([, , action]) => action === active)?.[1] ?? "Dashboard";
  const sidebar = app.querySelector(".dash-sidebar");
  const mainPane = app.querySelector(".dash-main-pane");
  const menuButton = app.querySelector("#dashboard-menu");
  const closeButton = app.querySelector(".dash-sidebar-close");
  const backdrop = app.querySelector(".dash-backdrop");
  const profileButton = app.querySelector("#dashboard-profile");
  const profileMenu = app.querySelector("#dashboard-profile-menu");
  const notificationArea = app.querySelector(".dash-notification-area");
  const notificationButton = app.querySelector("#dashboard-notifications");
  const notificationPanel = app.querySelector("#student-notification-panel");
  const compact = window.matchMedia("(max-width:1100px)");
  const lifecycle = new AbortController();
  const options = { signal: lifecycle.signal };
  window.addEventListener('resize', positionStudentNotificationPanel, options);

  shell.classList.toggle("dash-nav-hidden", dashboardSidebarCollapsed);
  function syncNavigation() {
    const open = compact.matches
      ? shell.classList.contains("dash-nav-open")
      : !shell.classList.contains("dash-nav-hidden");
    sidebar.inert = !open;
    mainPane.inert = compact.matches && open;
    menuButton.setAttribute("aria-expanded", String(open));
    backdrop.hidden = !(compact.matches && open);
    document.body.classList.toggle("dashboard-drawer-open", compact.matches && open);
  }
  function closeNavigation(returnFocus = true) {
    shell.classList.remove("dash-nav-open");
    syncNavigation();
    if (returnFocus) menuButton.focus();
  }
  function closeProfile() {
    profileMenu.hidden = true;
    profileButton.setAttribute("aria-expanded", "false");
  }
  menuButton.addEventListener(
    "click",
    () => {
      closeProfile();
      if (compact.matches) shell.classList.toggle("dash-nav-open");
      else {
        dashboardSidebarCollapsed = !dashboardSidebarCollapsed;
        shell.classList.toggle("dash-nav-hidden", dashboardSidebarCollapsed);
      }
      syncNavigation();
      if (compact.matches && shell.classList.contains("dash-nav-open")) closeButton.focus();
    },
    options,
  );
  closeButton.addEventListener("click", () => closeNavigation(), options);
  backdrop.addEventListener("click", () => closeNavigation(), options);
  compact.addEventListener(
    "change",
    () => {
      const focusWasInSidebar = sidebar.contains(document.activeElement);
      shell.classList.remove("dash-nav-open");
      shell.classList.toggle("dash-nav-hidden", dashboardSidebarCollapsed);
      closeProfile();
      syncNavigation();
      if (sidebar.inert && focusWasInSidebar) menuButton.focus();
    },
    options,
  );
  profileButton.addEventListener(
    "click",
    () => {
      profileMenu.hidden = !profileMenu.hidden;
      profileButton.setAttribute("aria-expanded", String(!profileMenu.hidden));
    },
    options,
  );
  document.addEventListener(
    "click",
    (event) => {
      if (!event.target.closest(".dash-profile")) closeProfile();
      if (notificationArea && !notificationArea.contains(event.target)) closeStudentNotificationPanel();
    },
    options,
  );
  document.addEventListener(
    "keydown",
    (event) => {
      if (event.key === "Escape") {
        if (shell.classList.contains("dash-nav-open")) closeNavigation();
        if (!profileMenu.hidden) {
          closeProfile();
          profileButton.focus();
        }
        if (notificationPanel && !notificationPanel.hidden) closeStudentNotificationPanel(true);
      }
      if (event.key === "Tab" && compact.matches && shell.classList.contains("dash-nav-open")) {
        const focusable = [...sidebar.querySelectorAll("button,a[href]")];
        const first = focusable[0],
          last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    },
    options,
  );
  const notices = {
    schedule: [
      "My Schedule",
      "September 18, 2026 · 8:00 AM – 4:00 PM\nChristian Formation Office, Recollection Hall\nStatus: Scheduled\n\nThis is the sample schedule shown in the dashboard preview.",
    ],
    announcements: [
      "Announcements",
      "Recollection 2026 — August 25, 2026\nRegistration is now open. Please submit your requirements on or before the deadline.\n\nBring Your Open Heart — August 20, 2026\nLet us prepare for a meaningful recollection experience.",
    ],
    notifications: [
      "Notifications",
      "The badge shows the reference design’s sample count of 3 unread notifications. Live notifications are not connected in this UI preview.",
    ],
  };
  if (window.CFO_BACKEND) {
    const user = window.CFO_BACKEND.user;
    const registration = cfoStudent.registrations.find(r=>!r.is_archived && r.year===cfoStudent.current_year);
    const schedule = registration?.schedule;
    app.querySelector(".dash-profile-name strong").textContent = user.name;
    app.querySelector(".dash-avatar").textContent =
      (user.first_name?.[0] || "") + (user.last_name?.[0] || "");
    updateStudentNotificationBell();
    if (!content) {
      app.querySelector("#dashboard-title").textContent = user.name + "!";
      const values = [
        user.status,
        registration ? registration.status+" · Receipt: "+registration.receipt_status : "Not registered",
        schedule?.date || "Not published",
        registration?.attendance || "Not Yet Recorded",
        registration?.certificate ? "Available" : "Not issued",
      ];
      app.querySelectorAll(".dash-status p").forEach((p, i) => {
        p.textContent = values[i];
      });
      const eventValues = [
        schedule?.date || "Not published",
        schedule?.time || "—",
        schedule?.venue || "—",
        schedule ? "Published" : "Awaiting CFO",
      ];
      app.querySelectorAll(".dash-event-info dd").forEach((dd, i) => {
        dd.textContent = eventValues[i];
      });
      app.querySelectorAll(".dash-announcement").forEach((item) => item.remove());
      app
        .querySelector(".dash-announcements")
        .insertAdjacentHTML("beforeend", (cfoStudent.announcements || []).slice(0, 2).map(a => '<article class="dash-announcement"><div><h3>' + escapeHTML(a.title) + '</h3><p>' + escapeHTML(a.preview) + '</p><a href="#announcements?announcement=' + a.id + '">Read announcement</a></div></article>').join('') || studentTemplate("html-dashboard-7"));
      app.querySelector(".dash-preview-note").textContent = "";
      app.querySelector(".dash-preview-note").hidden = true;
    }
    notices.schedule = [
      "My Schedule",
      schedule
        ? [schedule.title, schedule.date, schedule.time, schedule.venue].join("\n")
        : "The CFO has not published an annual schedule.",
    ];
    notices.announcements = ["Announcements", "No announcements have been published."];

  }
  // All sidebar, profile, brand, logout, and quick-action navigation shares this lifecycle.
  app.addEventListener(
    "click",
    (event) => {
      const control = event.target.closest('[data-dashboard-action], a[href^="#"]');
      if (!control || !app.contains(control)) return;
      // Preserve normal browser behavior for modified link clicks.
      if (
        control.matches("a") &&
        (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0)
      )
        return;
      const action = control.dataset.dashboardAction;
      const destination = control.matches("a")
        ? control.getAttribute("href")
        : studentNavigationRoutes[action];
      const wasProfileAction = profileMenu.contains(control);
      if (destination) event.preventDefault();
      if (compact.matches && shell.classList.contains("dash-nav-open")) closeNavigation();
      closeProfile();
      if (action === "logout") {
        openLogoutModal(wasProfileAction ? profileButton : compact.matches ? menuButton : control);
        return;
      }
      if (action === "notifications") {
        toggleStudentNotificationPanel();
        return;
      }
      if (control.closest(".dash-notification-item") && Number(cfoStudent.notification_unviewed_count || 0)) {
        markStudentNotificationsViewed().catch(() => {});
      }
      if (destination) {
        closeStudentNotificationPanel();
        if (location.hash === destination) {
          // Re-selecting the current page must not erase in-progress form values.
          const heading = app.querySelector("[data-notification-focus], #announcement-focus") || app.querySelector("h1");
          if (heading) {
            heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
          }
          if(heading?.matches("[data-notification-focus], #announcement-focus"))heading.scrollIntoView({block:"center"});
          else window.scrollTo({ top: 0, behavior: "instant" });
        } else location.hash = destination;
        return;
      }
      const notice = notices[action];
      if (!notice) return;
      if (wasProfileAction) profileButton.focus();
      showNotice(...notice);
    },
    options,
  );
  syncNavigation();
  const notificationClock = setInterval(() => { if(!document.hidden)renderStudentNotifications(); }, 60000);
  window.scrollTo({ top: 0, behavior: "instant" });
  dashboardCleanup = () => {
    clearInterval(notificationClock);
    lifecycle.abort();
    document.body.classList.remove("dashboard-drawer-open");
    dashboardCleanup = () => {};
  };
}

/* 7.3.7 — Recollection registration and receipt upload */
// Visual design and responsive layouts.

// This form is a local UI preview. Values and receipt files stay in page memory.
const registrationFieldLabels = {
  name:'Name',nickname:'Nickname',age:'Age',sex:'Sex',course:'Course / program',yearSection:'Year / section',email:'Email',phone:'Contact number',
  emergencyName:'Emergency contact',relationship:'Relationship',emergencyPhone:'Emergency contact number',medical:'Medical conditions',allergies:'Allergies',medications:'Medications',
  religion:'Religion',parish:'Parish',baptism:'Baptism',communion:'Holy Communion',confirmation:'Confirmation',ministry:'Ministry involvement',firstRecollection:'First Catholic recollection',
  previousRecollections:'Previous recollections',expectations:'Expectations',reflection:'Personal reflection',participation:'Participation consent',photos:'Photo consent'
};
const recollectionState = { step:1, values:{}, receipt:null, receiptUrl:'', draftKey:null, registrationId:null, baseline:{}, dirty:false, academicNotice:'' };
function recollectionAcademicGroups(schedules = cfoStudent.registration_schedules || []) {
  const groups=new Map();
  const list=(record,plural,singular)=>Array.isArray(record[plural])?record[plural].map(String):record[singular]?[String(record[singular])]:[];
  const isMap=value=>value&&typeof value==='object'&&!Array.isArray(value);
  for(const schedule of schedules) {
    const years=list(schedule,'year_levels','year_level');
    for(const course of schedule.courses||[]) {
      if(typeof course.name!=='string'||!course.name)continue;
      const id=String(course.id),pivot=course.pivot?.sections;
      const nested=isMap(schedule.course_sections_by_year?.[id])?schedule.course_sections_by_year[id]:isMap(pivot)?pivot:null;
      const mapped=Object.hasOwn(schedule.course_sections||{},id)?schedule.course_sections[id]:pivot;
      const flat=Array.isArray(mapped)?mapped:list(schedule,'sections','section');
      for(const year of years)for(const section of (nested?(Array.isArray(nested[year])?nested[year]:[]):flat)) {
        const group={course:course.name,yearLevel:String(year),section:String(section)};
        if(group.yearLevel&&group.section)groups.set(JSON.stringify(Object.values(group)),group);
      }
    }
  }
  return [...groups.values()];
}
function recollectionStudentCourse() {
  return String(cfoStudent.registration_course ?? window.CFO_BACKEND?.user?.course ?? '').trim();
}
function recollectionStudentAcademicGroups() {
  return recollectionAcademicGroups();
}
function recollectionAcademicChoices(values = recollectionState.values) {
  const groups=recollectionAcademicGroups(),unique=items=>[...new Set(items)],configured=type=>(cfoStudent.academic_options||[]).filter(option=>option.type===type).map(option=>String(option.name));
  return {
    course:unique(cfoStudent.registration_courses||[]).sort((a,b)=>a.localeCompare(b)),
    yearLevel:recollectionAcademicDropdownOptions('yearLevel',unique([...configured('year_level'),...groups.map(group=>group.yearLevel)])),
    section:recollectionAcademicDropdownOptions('section',unique([...configured('section'),...groups.map(group=>group.section)]))
  };
}
function cleanRecollectionAcademicValues(values) {
  const same=(left,right)=>String(left??'').trim().toLocaleUpperCase()===String(right).trim().toLocaleUpperCase();
  values.course=recollectionAcademicChoices(values).course.find(course=>same(values.course,course))||'';
  if(values.yearLevel===undefined&&values.section===undefined) {
    const choices=recollectionAcademicChoices(values);
    const candidates=choices.yearLevel.flatMap(yearLevel=>choices.section.map(section=>({yearLevel,section}))).filter(group=>[group.yearLevel+group.section,group.yearLevel+'-'+group.section].some(value=>same(values.yearSection,value)));
    if(candidates.length===1){values.yearLevel=candidates[0].yearLevel;values.section=candidates[0].section;}
  }
  values.yearLevel=recollectionAcademicChoices(values).yearLevel.find(year=>same(values.yearLevel,year))||'';
  values.section=recollectionAcademicChoices(values).section.find(section=>same(values.section,section))||'';
  values.yearSection=values.yearLevel&&values.section?values.yearLevel+values.section:'';
}
const recollectionAcademicLabels={course:'Course/Program',yearLevel:'Year Level',section:'Section'};
function recollectionAcademicDropdownOptions(name, available) {
  const standard=name==='yearLevel'?['1','2','3','4']:name==='section'?Array.from({length:26},(_,index)=>String.fromCharCode(65+index)):[];
  return [...new Set([...standard,...available])];
}
function recollectionAcademicFieldsHtml() {
  const choices=recollectionAcademicChoices(),values=recollectionState.values;
  const available=choices.course.length>0;
  return Object.entries(recollectionAcademicLabels).map(([name,label])=>{
    const id='recollection-'+name,selected=choices[name].includes(values[name]);
    const hint=!available?'No courses have been configured by the admin yet.':name==='course'?'Select your course/program.':'Select your year level and section.';
    const placeholder=name==='course'?'Select course/program':name==='yearLevel'?'Select year level':'Select section';
    const options=recollectionAcademicDropdownOptions(name,choices[name]).map(value=>'<option value="'+escapeHTML(value)+'"'+(value===values[name]&&selected?' selected':'')+(!choices[name].includes(value)?' disabled':'')+'>'+escapeHTML(value)+'</option>').join('');
    return '<fieldset class="recollection-academic-field" data-recollection-academic-field="'+name+'" tabindex="-1"><legend id="'+id+'-label">'+label+' <span class="cfo-required" aria-hidden="true">*</span></legend><select id="'+id+'" name="'+name+'" required data-recollection-academic="'+name+'" aria-labelledby="'+id+'-label" aria-describedby="'+id+'-hint '+id+'-error"><option value="" disabled'+(!selected?' selected':'')+'>'+placeholder+'</option>'+options+'</select><p id="'+id+'-hint" class="recollection-academic-hint">'+hint+'</p><p id="'+id+'-error" class="error" role="alert"></p></fieldset>';
  }).join('');
}
function validateRecollectionAcademicSelection(form, showError = false) {
  const choices=recollectionAcademicChoices();
  const values=recollectionState.values,same=(a,b)=>String(a).trim().toLocaleUpperCase()===String(b).trim().toLocaleUpperCase();
  const noSchedule=Object.keys(recollectionAcademicLabels).every(name=>choices[name].includes(values[name]))&&!recollectionAcademicGroups().some(group=>Object.keys(recollectionAcademicLabels).every(name=>same(group[name],values[name])));
  let invalid=null;
  for(const name of Object.keys(recollectionAcademicLabels)) {
    const valid=choices[name].includes(values[name])&&!(name==='section'&&noSchedule);
    if(!valid&&!invalid)invalid=name;
    const field=form.querySelector('[data-recollection-academic-field="'+name+'"]');
    if(!field)continue;
    const message=valid?'':name==='section'&&noSchedule?'No recollection schedule is available yet for '+values.course+' '+values.yearLevel+'-'+values.section+'.':'Select '+(name==='course'?'a course/program':name==='yearLevel'?'a year level':'a section')+' from the admin’s available options.';
    field.querySelector('.error').textContent=showError?message:'';
    field.setAttribute('aria-invalid',String(showError&&!valid));
    const input=field.querySelector('select');
    input.setAttribute('aria-invalid',String(showError&&!valid));
    input.setCustomValidity(message);
  }
  return invalid;
}
function refreshRecollectionAcademicFields(form) {
  const container=form.querySelector('#recollection-academic-options');
  if(!container)return;
  const focused=document.activeElement?.dataset?.recollectionAcademic;
  container.innerHTML=recollectionAcademicFieldsHtml();
  validateRecollectionAcademicSelection(form);
  if(focused) {
    const field=form.querySelector('[data-recollection-academic-field="'+focused+'"]');
    field.querySelector('select').focus({preventScroll:true});
  }
}
function bindRecollectionAcademicFields(form) {
  if(!form.querySelector('#recollection-academic-options'))return;
  form.addEventListener('change',event=>{
    const input=event.target,name=input.dataset?.recollectionAcademic;
    if(!Object.hasOwn(recollectionAcademicLabels,name||''))return;
    recollectionState.values[name]=input.value;
    if(name==='course'){recollectionState.values.yearLevel='';recollectionState.values.section='';}
    if(name==='yearLevel')recollectionState.values.section='';
    cleanRecollectionAcademicValues(recollectionState.values);
    recollectionState.dirty=true;form.dataset.dirty='true';
    refreshRecollectionAcademicFields(form);
  });
  form.addEventListener('invalid',event=>{
    if(event.target.dataset?.recollectionAcademic)validateRecollectionAcademicSelection(form,true);
  },true);
  validateRecollectionAcademicSelection(form);
}
function refreshRecollectionAcademicChoices() {
  if(!recollectionState.draftKey)return;
  const before=JSON.stringify([recollectionState.values.course,recollectionState.values.yearLevel,recollectionState.values.section]);
  cleanRecollectionAcademicValues(recollectionState.values);
  const changed=before!==JSON.stringify([recollectionState.values.course,recollectionState.values.yearLevel,recollectionState.values.section]);
  recollectionState.academicNotice=changed?'The admin updated the recollection options. Please choose your available course, year level and section again.':'The available course, year level and section options were updated by the admin.';
  const form=document.querySelector('#recollection-form');
  if(form)refreshRecollectionAcademicFields(form);
  const note=document.querySelector('#recollection-academic-update');
  if(note)note.textContent=recollectionState.academicNotice;
}
const recollectionPhoneError = 'Enter a valid mobile number like 09123456789.';
const recollectionEmergencyPhoneError = 'Emergency contact number must be different from your own number.';
function normalizeRecollectionPhone(value) {
  const digits=(typeof value==='string'||typeof value==='number'?String(value):'').replace(/[^0-9]/g,'');
  if(/^639[0-9]{9}$/.test(digits))return '0'+digits.slice(2);
  if(/^9[0-9]{9}$/.test(digits))return '0'+digits;
  return digits;
}
function isValidRecollectionPhone(value) {
  return typeof value==='string'&&value.length===11&&/^09[0-9]{9}$/.test(value)&&!/^09([0-9])\1{8}$/.test(value);
}
function cleanRecollectionPhoneValues(values) {
  for(const name of ['phone','emergencyPhone']) {
    const normalized=normalizeRecollectionPhone(values[name]);
    values[name]=isValidRecollectionPhone(normalized)?normalized:'';
  }
}
function sanitizeRecollectionPhoneInput(value) {
  return normalizeRecollectionPhone(value).slice(0,11);
}
function validateRecollectionPhoneField(input, form, showError = false) {
  const valid=isValidRecollectionPhone(input.value);
  const same=input.name==='emergencyPhone'&&valid&&input.value===form.elements.namedItem('phone')?.value;
  const message=valid?(same?recollectionEmergencyPhoneError:''):recollectionPhoneError;
  input.setCustomValidity(message);
  input.setAttribute('aria-invalid',String(showError&&Boolean(message)));
  input.closest('.recollection-field').querySelector('.error').textContent=showError?message:'';
  return !message;
}
function bindRecollectionPhoneFields(form) {
  for(const name of ['phone','emergencyPhone']) {
    const input=form.elements.namedItem(name);
    if(!input)continue;
    const hasError=field=>Boolean(field.closest('.recollection-field').querySelector('.error').textContent);
    const refresh=(showError=false)=>{
      validateRecollectionPhoneField(input,form,showError);
      const emergency=form.elements.namedItem('emergencyPhone');
      if(name==='phone'&&emergency)validateRecollectionPhoneField(emergency,form,hasError(emergency));
    };
    input.addEventListener('input',()=>{
      const caret=input.selectionStart;
      const prefix=caret===null?'':sanitizeRecollectionPhoneInput(input.value.slice(0,caret));
      input.value=sanitizeRecollectionPhoneInput(input.value);
      if(caret!==null)input.setSelectionRange(Math.min(prefix.length,input.value.length),Math.min(prefix.length,input.value.length));
      refresh(hasError(input));
    });
    const insertText=text=>{
      const start=input.selectionStart??input.value.length,end=input.selectionEnd??start;
      const available=Math.max(0,11-input.value.length+(end-start));
      const cleaned=sanitizeRecollectionPhoneInput(text).slice(0,available);
      if(!cleaned&&start===end)return;
      const prefix=input.value.slice(0,start)+cleaned;
      input.value=sanitizeRecollectionPhoneInput(prefix+input.value.slice(end));
      const caret=Math.min(sanitizeRecollectionPhoneInput(prefix).length,input.value.length);
      input.setSelectionRange(caret,caret);
      input.dispatchEvent(new Event('input',{bubbles:true}));
    };
    // Clean the complete clipboard value before the browser applies maxlength.
    input.addEventListener('paste',event=>{
      if(!event.clipboardData)return;
      event.preventDefault();
      insertText(event.clipboardData.getData('text'));
    });
    input.addEventListener('beforeinput',event=>{
      if(!event.cancelable||event.isComposing||!event.inputType.startsWith('insert')||typeof event.data!=='string')return;
      event.preventDefault();
      insertText(event.data);
    });
    input.addEventListener('blur',()=>refresh(true));
    input.addEventListener('invalid',()=>refresh(true));
    input.value=sanitizeRecollectionPhoneInput(input.value);
    refresh();
  }
}
function validateRecollectionReligionField(input, showError = false) {
  const valid=Boolean(input.value.trim());
  const message=valid?'':'Please select your religion.';
  input.setCustomValidity(message);
  input.setAttribute('aria-invalid',String(showError&&!valid));
  input.closest('.recollection-field').querySelector('.error').textContent=showError?message:'';
  return valid;
}
function bindRecollectionReligionField(form) {
  const input=form.elements.namedItem('religion');
  if(!input)return;
  input.addEventListener('change',()=>{
    recollectionState.values.religion=input.value;
    recollectionState.dirty=true;
    validateRecollectionReligionField(input,Boolean(input.closest('.recollection-field').querySelector('.error').textContent));
  });
  input.addEventListener('invalid',()=>validateRecollectionReligionField(input,true));
  validateRecollectionReligionField(input);
}
function registrationDisplayValue(key,value) {
  if(['participation','photos'].includes(key))return [true,1,'1'].includes(value)?'Agreed':'Not agreed';
  return value === null || value === undefined || value === '' ? '(Not provided)' : String(value);
}
function registrationReview(values, baseline = null) {
  const displayValue=(record,key)=>key==='yearSection'&&record.yearLevel&&record.section?record.yearLevel+' / '+record.section:record[key];
  return Object.entries(registrationFieldLabels).flatMap(([key,label])=>{
    const after=registrationDisplayValue(key,displayValue(values,key));
    const before=baseline?registrationDisplayValue(key,displayValue(baseline,key)):undefined;
    return before===after?[]:[{label,before,after}];
  });
}
function initializeRecollectionDraft(registration = null) {
  const draftKey=registration?(registration.can_reregister?'retry-':'edit-')+registration.id:'new-'+cfoStudent.current_year;
  if(recollectionState.draftKey===draftKey){cleanRecollectionAcademicValues(recollectionState.values,true);cleanRecollectionPhoneValues(recollectionState.values);return;}
  clearRecollection();
  const user=window.CFO_BACKEND.user;
  const previous=cfoStudent.registrations.filter(r=>r.year<cfoStudent.current_year).sort((a,b)=>b.year-a.year||b.id-a.id)[0];
  const source=registration||previous;
  const defaults={name:user.name,course:recollectionStudentCourse(),email:user.email,yearSection:[user.year_level,user.section].filter(Boolean).join('-')};
  const values=Object.fromEntries(Object.keys(registrationFieldLabels).map(key=>[key,registration?(registration.details?.[key]??''):(source?.details?.[key]??defaults[key]??'')]));
  if(source)values.yearSection=source.details?.yearSection??'';
  if(!registration)values.course=defaults.course;
  if(source?.details?.yearLevel!==undefined||source?.details?.section!==undefined) {
    values.yearLevel=source.details.yearLevel;
    values.section=source.details.section;
  } else if(!source) {
    values.yearLevel=user.year_level;
    values.section=user.section;
  }
  if(!registration&&source&&String(source.details?.course??'').trim().toLocaleUpperCase()!==defaults.course.toLocaleUpperCase()) {
    values.yearLevel=user.year_level;values.section=user.section;values.yearSection=defaults.yearSection;
  }
  cleanRecollectionAcademicValues(values);
  cleanRecollectionPhoneValues(values);
  for(const key of ['participation','photos'])values[key]=registration&&!registration.can_reregister?[true,1,'1'].includes(values[key]):false;
  Object.assign(recollectionState,{draftKey,registrationId:registration?.id||null,retrying:Boolean(registration?.can_reregister),values,baseline:registration?{...registration.details}:{},sourceYear:previous?.year,dirty:false});
}
function showSubmittedRegistration(record) {
  const root=document.querySelector('#recollection-root');
  const canEdit=!record.is_archived&&record.status==='Pending';
  const schedule=record.schedule;
  const summary=[['Registration status',record.status],['Attendance',record.attendance||'Not Yet Recorded'],['Attempt',record.attempt_no||1],['Receipt status',record.receipt_status||'Not uploaded'],['Schedule status',schedule?'Scheduled':'Not Yet Scheduled'],
    ...(schedule?[['Recollection',schedule.title],['Date',schedule.date],['Time',schedule.time],['Venue',schedule.venue]]:[]),
    ...(record.receipt_reason?[['Receipt review reason',record.receipt_reason]]:[]),
    ...(record.rejection_reason?[['Registration review reason',record.rejection_reason]]:[])];
  const reviewRows=registrationReview(record.details);
  const detailGroups=[
    ['Personal information',['name','nickname','age','sex','course','yearSection','email','phone'],'wide'],
    ['Emergency information',['emergencyName','relationship','emergencyPhone'],''],
    ['Health and safety',['medical','allergies','medications'],''],
    ['Faith and sacramental background',['religion','parish','baptism','communion','confirmation','ministry'],'wide'],
    ['Recollection experience',['firstRecollection','previousRecollections'],''],
    ['Expectations and intentions',['expectations'],''],
    ['Personal reflection',['reflection'],''],
    ['Consent and commitment',['participation','photos'],'']
  ];
  const detailMarkup=detailGroups.map(([heading,keys,size])=>'<section class="submitted-registration-card submitted-registration-details '+size+'"><h2>'+escapeHTML(heading)+'</h2><dl>'+keys.map(key=>{
    const entry=reviewRows.find(row=>row.label===registrationFieldLabels[key]);
    return entry?'<div class="submitted-registration-detail"><dt>'+escapeHTML(entry.label)+'</dt><dd>'+(entry.label==='Email'?studentEmailDisplay(entry.after):escapeHTML(entry.after))+'</dd></div>':'';
  }).join('')+'</dl></section>').join('');
  const receiptMarkup=window.CfoStudentReceipt.markup(record);
  const statusMarkup='<section class="submitted-registration-card submitted-registration-review" aria-label="Registration review and schedule"><h2>Review and schedule</h2><dl>'+summary.map(([label,value])=>'<div class="submitted-registration-review-row"><dt>'+escapeHTML(label)+'</dt><dd>'+(['Registration status','Receipt status'].includes(label)?'<span class="submitted-registration-badge" data-status="'+escapeHTML(value||'')+'">'+escapeHTML(value||'?')+'</span>':escapeHTML(value||'?'))+'</dd></div>').join('')+'</dl>'+(!record.is_archived&&['Rejected','Not uploaded'].includes(record.receipt_status)?'<p class="submitted-registration-receipt-action"><a class="outline" href="#dashboard?focus=registration">Upload or replace receipt</a></p>':'')+'</section>';
  root.innerHTML='<section class="submitted-registration"><header class="submitted-registration-header"><h1>My Recollection Registration</h1><p class="submitted-registration-meta"><span>Registration year: '+escapeHTML(record.year)+'</span><span class="submitted-registration-badge" data-status="'+escapeHTML(record.status)+'">'+escapeHTML(record.status)+'</span></p><p class="submitted-registration-note">'+escapeHTML(canEdit?'You can edit your submitted information until the CFO approves it.':'This registration is locked for student edits. Visit the CFO office if a correction is needed.')+'</p><div class="submitted-registration-actions">'+(canEdit?'<a class="primary" href="#recollection-registration?edit=1">Edit registration</a>':'')+'<a class="outline" href="#dashboard">Back to dashboard</a></div></header><div class="submitted-registration-overview'+(receiptMarkup?' has-receipt':'')+'">'+statusMarkup+receiptMarkup+'</div><div class="submitted-registration-information">'+detailMarkup+'</div></section>';
}
function clearRecollection() {
  if (recollectionState.receiptUrl) URL.revokeObjectURL(recollectionState.receiptUrl);
  Object.assign(recollectionState, { step: 1, values: {}, receipt: null, receiptUrl: "", scheduleId: "", draftKey:null, registrationId:null, retrying:false, baseline:{}, dirty:false, sourceYear:null, academicNotice:'' });
}

function recollectionRegistration() {
  dashboard({ content: studentTemplate("html-recollectionregistration-1"), active: "registration" });
  Object.assign(paths, {
    phone: "M6 2l4 5-3 3c2 4 3 5 7 7l3-3 5 4c0 5-4 5-7 4C8 20 3 15 2 8 1 5 2 2 6 2Z",
    heart: "M12 21S2 15 2 8c0-6 8-7 10-2 2-5 10-4 10 2 0 7-10 13-10 13Z",
    church: "M12 1v6M9 3h6M8 13V9l4-3 4 3v4l5 3v6H3v-6ZM10 22v-7h4v7",
    cross: "M10 2h4v6h6v4h-6v10h-4V12H4V8h6Z",
    upload: "M12 16V3M6 9l6-6 6 6M3 16v5h18v-5",
    paperclip: "M8 17l9-9a3 3 0 0 0-4-4L3 14a5 5 0 0 0 7 7L21 10M7 15l8-8",
    refresh: "M20 8a9 9 0 0 0-16 1M20 3v5h-5M4 16a9 9 0 0 0 16-1M4 21v-5h5",
    trash: "M3 6h18M8 6V3h8v3M5 6l1 16h12l1-16M10 10v8M14 10v8",
  });
  if (window.CFO_BACKEND) {
    const current=cfoStudent.registrations.find(r=>r.year===cfoStudent.current_year);
    const editing=new URLSearchParams(location.hash.split('?')[1]||'').get('edit')==='1';
    if(current&&!current.can_reregister&&(current.is_archived||!editing||current.status!=='Pending')){clearRecollection();showSubmittedRegistration(current);return;}
    initializeRecollectionDraft(current||null);
  }
  renderRecollectionStep();
}

function recollectionField(name, label, placeholder, type = "text", required = false, choices = null) {
  const value = recollectionState.values[name] ?? "";
  const id = `recollection-${name}`;
  const attrs = `id="${id}" name="${name}" ${required ? "required" : ""}${name==='religion'?` aria-describedby="${id}-error"`:''}`;
  const phoneField=['phone','emergencyPhone'].includes(name);
  const phoneAttrs=phoneField?`pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" aria-describedby="${id}-error"`:'';
  if(phoneField)type='tel';
  const autocomplete=name==='phone'?'tel-national':'off';
  let control;
  if (choices && value && !choices.includes(value)) choices=[value,...choices];
  if (choices)
    control = studentTemplate("html-recollectionfield-2", [
      attrs,
      placeholder,
      choices
        .map((choice) =>
          studentTemplate("html-recollectionfield-1", [
            escapeHTML(choice),
            value === choice ? "selected" : "",
            escapeHTML(choice),
          ]),
        )
        .join(""),
    ]);
  else if (type === "textarea")
    control = studentTemplate("html-recollectionfield-3", [attrs, placeholder, escapeHTML(value)]);
  else
    control = `<input ${attrs} type="${type}" ${type === "number" ? 'min="10" max="100" step="1"' : ""} ${phoneAttrs} value="${escapeHTML(value)}" placeholder="${placeholder}" autocomplete="${autocomplete}">`;
  if(name==='religion') {
    control=control.replace('<option value="">',`<option value="" disabled ${value?'':'selected'}>`);
    label+=' <span class="cfo-required" aria-hidden="true">*</span>';
  }
  if(['phone','emergencyPhone','religion'].includes(name))control+=`<p id="${id}-error" class="error" role="alert"></p>`;
  return studentTemplate("html-recollectionfield-4", [id, label, control]);
}

function recollectionGroup(symbol, title, content) {
  return studentTemplate("html-recollectiongroup-1", [icon(symbol), title, content]);
}

function renderRecollectionStep(focusHeading = false) {
  cleanRecollectionAcademicValues(recollectionState.values);
  const root = document.querySelector("#recollection-root");
  const second = recollectionState.step === 2;
  root.className = second ? "recollection-step-2" : "recollection-step-1";
  const f = recollectionField;
  const yesNo = ["Yes", "No"];
  let groups;
  if (!second) {
    groups =
      recollectionGroup(
        "person",
        "I. PERSONAL INFORMATION",
        studentTemplate("html-renderrecollectionstep-1", [
          f("name", "Name:", "Enter your complete name", "text", true),
          f("nickname", "Nickname:", "Enter your nickname"),
          f("age", "Age:", "Enter your age", "number", true),
          f("sex", "Sex:", "Select", "text", true, ["Female", "Male", "Prefer not to say"]),
          '<div id="recollection-academic-options" class="recollection-academic-fields">'+recollectionAcademicFieldsHtml()+'</div>',
          f("email", "Email:", "Enter your email address", "email", true),
          f("phone", "Contact Number:", "Enter your contact number", "tel", true),
        ]),
      ) +
      recollectionGroup(
        "phone",
        "II. EMERGENCY INFORMATION",
        studentTemplate("html-renderrecollectionstep-2", [
          f("emergencyName", "Emergency Contact Person:", "Enter name of emergency contact", "text", true),
          f("relationship", "Relationship:", "Enter relationship", "text", true),
          f("emergencyPhone", "Contact Number:", "Enter contact number", "tel", true),
        ]),
      ) +
      recollectionGroup(
        "heart",
        "III. HEALTH AND SAFETY",
        studentTemplate("html-renderrecollectionstep-3", [
          f("medical", "Medical Conditions (If any):", "Enter medical conditions (if any)"),
          f("allergies", "Allergies:", "Enter allergies (if any)"),
          f("medications", "Medications Currently Taking:", "Enter medications currently taking (if any)"),
        ]),
      );
  } else {
    groups =
      recollectionGroup(
        "cross",
        "IV. FAITH AND SACRAMENTAL BACKGROUND",
        studentTemplate("html-renderrecollectionstep-4", [
          f("religion", "Religion:", "Select your religion", "text", true, [
            "Roman Catholic",
            "Protestant / Christian",
            "Iglesia ni Cristo",
            "Islam",
            "Other",
            "Prefer not to say",
          ]),
          f("parish", "Parish (if any):", "Enter your parish (if any)"),
          f("baptism", "Baptism", "Select", "text", false, yesNo),
          f("communion", "Holy Communion", "Select", "text", false, yesNo),
          f("confirmation", "Confirmation", "Select", "text", false, yesNo),
          f(
            "ministry",
            "Church/Campus Ministry Involvement:",
            "Enter your church or campus ministry involvement (if any)",
          ),
        ]),
      ) +
      recollectionGroup(
        "people",
        "V. RECOLLECTION EXPERIENCE",
        studentTemplate("html-renderrecollectionstep-5", [
          f("firstRecollection", "Is this your first Catholic recollection?", "Select", "text", false, yesNo),
          f(
            "previousRecollections",
            "Previous Recollections Attended (if any):",
            "Enter previous recollections attended (if any)",
          ),
        ]),
      ) +
      recollectionGroup(
        "leaf",
        "VI. EXPECTATIONS AND INTENTIONS",
        f(
          "expectations",
          "What do you hope to receive or experience during this recollection?",
          "Enter your answer here...",
          "textarea",
        ),
      ) +
      recollectionGroup(
        "heart",
        "VII. PERSONAL REFLECTION (Optional)",
        f(
          "reflection",
          "Prayers intentions, struggles, or concerns you wish to share:",
          "Enter your answer here...",
          "textarea",
        ),
      ) +
      recollectionGroup(
        "shield",
        "VIII. CONSENT AND COMMITMENT",
        studentTemplate("html-renderrecollectionstep-6", [
          recollectionState.values.participation ? "checked" : "",
          recollectionState.values.photos ? "checked" : "",
        ]),
      ) +
      recollectionGroup(
        "paperclip",
        "XII. RECEIPT UPLOAD",
        studentTemplate("html-renderrecollectionstep-7", [icon("upload")]),
      );
  }
  groups='<p id="recollection-academic-update" class="recollection-academic-update" role="status">'+escapeHTML(recollectionState.academicNotice)+'</p>'+groups;
  root.innerHTML = studentTemplate("html-renderrecollectionstep-12", [
    second
      ? "Complete the remaining information to finalize your registration."
      : "Please fill out the form completely and honestly.",
    second
      ? studentTemplate("html-renderrecollectionstep-8", [icon("left")])
      : studentTemplate("html-renderrecollectionstep-9", [icon("left")]),
    !second ? 'aria-current="step" class="is-active"' : "",
    second ? 'aria-current="step" class="is-active"' : "",
    icon(second ? "church" : "people"),
    second ? "2: Faith Background to Consent" : "1: Personal Information to Health and Safety",
    second ? "Please complete the following information." : "Please provide the following information.",
    groups,
    second
      ? studentTemplate("html-renderrecollectionstep-10", [icon("left"), icon("send")])
      : studentTemplate("html-renderrecollectionstep-11", [icon("info"), icon("right")]),
    icon("close"),
  ]);
  const form = root.querySelector("#recollection-form");
  bindRecollectionAcademicFields(form);
  bindRecollectionPhoneFields(form);
  bindRecollectionReligionField(form);
  protectStudentEmailInput(form.querySelector('input[type=email]'));
  form.dataset.dirty=String(recollectionState.dirty);
  if (window.CFO_BACKEND) {
    const editing=Boolean(recollectionState.registrationId);
    const note=root.querySelector('.recollection-local-note');
    note.textContent=recollectionState.retrying?'You were marked Absent. Review your information and confirm consent again to resubmit. The missed attempt stays in History; the CFO will review this registration and assign a new schedule.':editing?'Editing your Pending registration. Review the changes before saving.':recollectionState.sourceYear?'Your details from your last registration were filled in. Please review and update anything that changed.':'Your account information is filled where available. Review and complete your registration.';
    if(!editing&&recollectionState.sourceYear)form.prepend(note);
    if(second&&editing)form.querySelector('[type=submit]').textContent=recollectionState.retrying?'Resubmit Registration':'Save Changes';
  }

  form.addEventListener("input", (event) => {
    const input = event.target;
    recollectionState.dirty=true;
    if (input.name&&!input.dataset.recollectionAcademic)
      recollectionState.values[input.name] = input.type === "checkbox" ? input.checked : input.value;
  });
  root.querySelectorAll("[data-recollection-back]").forEach(
    (button) =>
      (button.onclick = () => {
        recollectionState.step = 1;
        renderRecollectionStep(true);
      }),
  );
  form.onsubmit = (event) => {
    event.preventDefault();
    cleanRecollectionAcademicValues(recollectionState.values);
    const invalidName=validateRecollectionAcademicSelection(form);
    if(invalidName){
      if(second){recollectionState.step=1;renderRecollectionStep(true);}
      const academicForm=root.querySelector('#recollection-form');
      validateRecollectionAcademicSelection(academicForm,true);
      const field=academicForm.querySelector('[data-recollection-academic-field="'+invalidName+'"]');
      field.querySelector('select').focus();
      return;
    }
    for(const name of ['phone','emergencyPhone'])recollectionState.values[name]=normalizeRecollectionPhone(recollectionState.values[name]);
    const invalidPhone=!isValidRecollectionPhone(recollectionState.values.phone)?'phone':!isValidRecollectionPhone(recollectionState.values.emergencyPhone)||recollectionState.values.emergencyPhone===recollectionState.values.phone?'emergencyPhone':null;
    if(invalidPhone){
      if(second){recollectionState.step=1;renderRecollectionStep(true);}
      const phoneForm=root.querySelector('#recollection-form');
      for(const name of ['phone','emergencyPhone'])validateRecollectionPhoneField(phoneForm.elements.namedItem(name),phoneForm,true);
      phoneForm.elements.namedItem(invalidPhone).focus();
      return;
    }
    if(second&&!String(recollectionState.values.religion??'').trim()) {
      const input=form.elements.namedItem('religion');
      validateRecollectionReligionField(input,true);
      input.focus();
      return;
    }
    if (!second) {
      recollectionState.step = 2;
      renderRecollectionStep(true);
    } else if (window.CFO_BACKEND) {
      const editing=Boolean(recollectionState.registrationId);
      if(!editing&&recollectionState.sourceYear&&!recollectionState.receipt){
        root.querySelector('#receipt-error').textContent='Upload a new receipt for this registration.';
        root.querySelector('#recollection-receipt').focus();
        return;
      }
      const payload=new FormData();
      const values={...recollectionState.values};
      for(const [key,value] of Object.entries(values))payload.append('details['+key+']',typeof value==='boolean'?(value?'1':'0'):value??'');
      if(recollectionState.receipt)payload.append('receipt',recollectionState.receipt);
      const review=registrationReview(values,editing&&!recollectionState.retrying?recollectionState.baseline:null);
      if(recollectionState.retrying)review.unshift({label:'New attempt',after:'Resubmit after absence. The missed attempt will remain in History.'});
      if(recollectionState.receipt)review.push({label:'Receipt',before:editing?'Previously submitted receipt (if any)':undefined,after:recollectionState.receipt.name});
      if(editing&&!review.length){showNotice('No changes','Change a field before saving.');return;}
      const url=editing?'/api/registrations/'+recollectionState.registrationId+(recollectionState.retrying?'/retry':''):'/api/registrations';
      // Method override lets PHP parse multipart file uploads for the PATCH route.
      if(editing&&!recollectionState.retrying)payload.append('_method','PATCH');
      cfoSubmit(form,async()=>{
        await cfoRequest(url,'POST',payload);
        clearRecollection();
        location.hash='dashboard';
        setTimeout(()=>showNotice(editing?'Changes saved':'Registration saved','Your registration is Pending. You can edit it until the CFO approves it.'),100);
      },false,review);
    } else
      showNotice(
        "Registration preview complete",
        "Both sections are complete in this UI preview. No registration, consent, personal information, or receipt has been submitted or saved.",
      );
  };
  const dialog = root.querySelector("#receipt-dialog");
  dialog.querySelector("button").onclick = () => dialog.close();
  dialog.addEventListener("close", () => {
    root.querySelector("#receipt-dialog-content").replaceChildren();
  });
  if (second) {
    bindRecollectionReceipt();
    const record=cfoStudent.registrations.find(r=>r.id===recollectionState.registrationId);
    if(record?.has_receipt){
      const note=document.createElement('p');
      note.innerHTML='Current receipt: <a target="_blank" rel="noopener" href="/api/receipts/'+record.id+'">View uploaded receipt</a>. '+escapeHTML(recollectionState.retrying?'Leave the upload empty to submit a copy of this receipt for CFO review, or upload a new receipt.':record.receipt_status==='Approved'?'Verified by the CFO; this file is locked.':'Leave the upload empty to keep this receipt.');
      root.querySelector('.recollection-drop').before(note);
      if(record.receipt_status==='Approved'&&!recollectionState.retrying){
        root.querySelector('#recollection-receipt').disabled=true;
        root.querySelector('.recollection-drop').style.display='none';
      }
    }
  }
  if (focusHeading) {
    root.querySelector("h1").focus({ preventScroll: true });
    window.scrollTo({ top: 0, behavior: "instant" });
  }
}

function bindRecollectionReceipt() {
  const input = document.querySelector("#recollection-receipt");
  input.accept="image/jpeg,image/png";
  const drop = document.querySelector(".recollection-drop");
  function selectFile(file) {
    const error = document.querySelector("#receipt-error");
    error.textContent = "";
    if (!file) return;
    if (!["image/jpeg", "image/png"].includes(file.type)) {
      error.textContent = "Choose a JPG or PNG file.";
      input.value = "";
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      error.textContent = "The receipt must be 5MB or smaller.";
      input.value = "";
      return;
    }
    if (recollectionState.receiptUrl) URL.revokeObjectURL(recollectionState.receiptUrl);
    recollectionState.dirty=true;
    input.closest("form").dataset.dirty="true";
    recollectionState.receipt = file;
    recollectionState.receiptUrl = URL.createObjectURL(file);
    updateRecollectionReceipt();
  }
  input.onchange = () => selectFile(input.files[0]);
  drop.ondragover = (event) => {
    event.preventDefault();
    drop.classList.add("is-dragging");
  };
  drop.ondragleave = () => drop.classList.remove("is-dragging");
  drop.ondrop = (event) => {
    event.preventDefault();
    drop.classList.remove("is-dragging");
    selectFile(event.dataTransfer.files[0]);
  };
  updateRecollectionReceipt();
}

function updateRecollectionReceipt() {
  const preview = document.querySelector("#receipt-preview");
  const status = document.querySelector("#receipt-status");
  const file = recollectionState.receipt;
  const saved = window.CFO_BACKEND ? cfoStudent.registrations.find(record=>String(record.id)===String(recollectionState.registrationId)) : null;
  status.textContent = file ? "Pending Verification" : saved?.has_receipt ? (saved.receipt_status || "Pending") : "No receipt selected";
  status.classList.toggle("has-receipt", Boolean(file || saved?.has_receipt));
  if (!file) {
    preview.innerHTML = window.CfoStudentReceipt.preview(saved) || studentTemplate("html-updaterecollectionreceipt-1");
    return;
  }
  const isImage = file.type.startsWith("image/");
  preview.innerHTML = studentTemplate("html-updaterecollectionreceipt-3", [
    isImage
      ? `<img src="${recollectionState.receiptUrl}" alt="Selected receipt preview">`
      : studentTemplate("html-updaterecollectionreceipt-2", [icon("file")]),
    icon("eye", "recollection-preview-eye"),
    escapeHTML(file.name),
    icon("refresh"),
    icon("trash"),
  ]);
  document.querySelector("#receipt-replace").onclick = () =>
    document.querySelector("#recollection-receipt").click();
  document.querySelector("#receipt-remove").onclick = () => {
    URL.revokeObjectURL(recollectionState.receiptUrl);
    recollectionState.receipt = null;
    recollectionState.receiptUrl = "";
    document.querySelector("#recollection-receipt").value = "";
    document.querySelector("#receipt-error").textContent = "";
    updateRecollectionReceipt();
    document.querySelector("#recollection-receipt").focus();
  };
  preview.querySelector(".recollection-receipt-thumb").onclick = () => {
    const content = document.querySelector("#receipt-dialog-content");
    const media = document.createElement(isImage ? "img" : "object");
    if (isImage) {
      media.src = recollectionState.receiptUrl;
      media.alt = "Selected receipt";
    } else {
      media.data = recollectionState.receiptUrl;
      media.type = "application/pdf";
      media.setAttribute("aria-label", "Selected PDF receipt");
      media.textContent = "PDF preview is not available in this browser.";
    }
    content.replaceChildren(media);
    document.querySelector("#receipt-dialog").showModal();
  };
}

/* 7.3.8 — Schedule change requests */
// Live records and the clearly labelled file preview share the existing student shell.
const studentChangeFilters = {search: "", status: ""};
function studentChangeCharacterCount(value) {
  return Array.from(String(value || "")).length;
}
function studentChangeStatus(request) {
  return ["Rejected", "Closed"].includes(request.status) ? "Not Approved" : request.student_status || request.status || "Pending";
}
function studentChangeRequestNumber(request, year) {
  return "SCR-" + (String(request.created_at || "").slice(0, 4) || year) + "-" + String(request.id).padStart(3, "0");
}
function studentChangeDate(value, withTime = false) {
  if (!value) return "—";
  const date = new Date(withTime ? value : String(value).slice(0, 10) + "T00:00:00Z");
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("en-PH", {year: "numeric", month: "short", day: "numeric", timeZone: withTime ? "Asia/Manila" : "UTC", ...(withTime ? {hour: "numeric", minute: "2-digit"} : {})}).format(date);
}
function studentChangeScheduleText(schedule) {
  return schedule ? [schedule.title, studentChangeDate(schedule.date), schedule.time, schedule.venue].filter(Boolean).join(" · ") : "Not assigned";
}
function studentChangeCurrentSchedule(request, state) {
  return request.current_schedule || state.schedules?.find(schedule => String(schedule.id) === String(request.current_schedule_id)) || state.registrations?.find(registration => String(registration.id) === String(request.registration_id))?.schedule;
}
function studentChangeBadge(request) {
  const status = studentChangeStatus(request);
  return `<span class="student-change-status" data-status="${escapeHTML(status)}">${escapeHTML(status)}</span>`;
}
function studentChangeStatsHtml(requests) {
  return [["Pending", "Waiting for CFO review", "clock"], ["Approved", "New schedule assigned", "check"], ["Not Approved", "Review the CFO response", "info"], ["Cancelled", "Cancelled by you", "close"]].map(([status, help, symbol]) => `<article class="student-change-stat"><div class="student-change-stat-head"><span>${escapeHTML(status)}</span>${icon(symbol)}</div><strong class="student-change-stat-count">${requests.filter(request => studentChangeStatus(request) === status).length}</strong><small>${escapeHTML(help)}</small></article>`).join("");
}
function studentChangeRowsHtml(requests, state) {
  return requests.map(request => `<tr><td><strong>${escapeHTML(studentChangeDate(request.created_at, true))}</strong><small class="student-change-subtext">${escapeHTML(studentChangeRequestNumber(request, state.current_year))}</small></td><td>${escapeHTML(studentChangeScheduleText(studentChangeCurrentSchedule(request, state)))}</td><td><div class="student-change-reason">${escapeHTML(request.reason)}</div></td><td>${studentChangeBadge(request)}</td><td><p>${escapeHTML(request.student_message || "Awaiting CFO review.")}</p>${request.assigned_schedule_id && request.assigned_schedule ? `<p class="student-change-subtext"><strong>New schedule:</strong> ${escapeHTML(studentChangeScheduleText(request.assigned_schedule))}</p>` : ""}</td><td><div class="student-change-actions"><button type="button" class="outline" data-student-change-action="view" data-id="${escapeHTML(request.id)}">View</button>${request.status === "Pending" ? `<button type="button" class="outline" data-student-change-action="cancel" data-id="${escapeHTML(request.id)}">Cancel</button>` : ""}</div></td></tr>`).join("");
}
const studentChangeDemoState = {
  current_year: 2026, registrations: [{id: 1, year: 2026, attendance: "Not Yet Recorded", recollection_schedule_id: 1, schedule: {id: 1, title: "Annual Recollection (Demo)", date: "2026-10-24", time: "8:00 AM – 4:00 PM", venue: "Campus Chapel"}}],
  requests: [{id: 1, registration_id: 1, current_schedule_id: 1, current_schedule: {title: "Annual Recollection (Demo)", date: "2026-10-24"}, created_at: "2026-10-08T09:20:00+08:00", reason: "I have an academic activity on the same day and would like another available date.", status: "Pending", student_message: "Your request is pending CFO approval. Your current schedule remains assigned."}, {id: 2, registration_id: 1, current_schedule_id: 1, current_schedule: {title: "Annual Recollection (Demo)", date: "2026-09-20"}, created_at: "2026-09-18T13:40:00+08:00", reason: "I have a family commitment on the assigned date.", status: "Closed", student_message: "This request was closed because your schedule changed."}]
};
function scheduleChangeRequest() {
  if (window.CFO_BACKEND) { cfoScheduleChange(); return; }
  renderStudentScheduleChange(studentChangeDemoState, false);
}
function cfoScheduleChange() {
  renderStudentScheduleChange(cfoStudent, true);
}
function renderStudentScheduleChange(state, backend = true) {
  const eligible = (state.registrations || []).filter(registration => !registration.is_archived && registration.year === state.current_year && registration.schedule && registration.attendance === "Not Yet Recorded").sort((a, b) => b.id - a.id);
  const registration = eligible[0];
  const requests = [...(state.requests || [])].sort((a, b) => String(b.created_at || "").localeCompare(String(a.created_at || "")) || b.id - a.id);
  const pending = requests.some(request => request.status === "Pending");
  const available = Boolean(registration && !pending);
  const availability = pending ? "Your existing request is pending. Wait for the CFO decision or cancel it before submitting another request." : !registration ? "You have no eligible current schedule. A request requires an assigned current-year recollection with attendance not yet recorded." : "Your current schedule stays assigned until the CFO approves your request and assigns a new schedule.";
  dashboard({active: "change", titleId: "change-title", content: studentTemplate("html-student-change-page", [studentChangeStatsHtml(requests), escapeHTML(studentChangeScheduleText(registration?.schedule)), escapeHTML(availability), available ? "" : "disabled", icon("send"), backend ? "" : '<p class="student-change-callout">UI preview · Sample records. Requests and cancellations here are not sent or saved.</p>'])});
  const root = document.querySelector("#student-change-page");
  const pageRoute = typeof location === "undefined" ? null : location.hash;
  const pageVersion = typeof routeVersion === "undefined" ? null : routeVersion;
  const stillCurrentPage = () => root.isConnected && (pageRoute === null || location.hash === pageRoute) && (pageVersion === null || routeVersion === pageVersion);
  const form = root.querySelector("#cfo-change-form"), reason = root.querySelector("#change-reason"), fields = root.querySelector("#student-change-fields");
  const submit = form.querySelector("[type=submit]"), error = root.querySelector("#change-reason-error");
  fields.disabled = !available;
  reason.disabled = !available;
  submit.disabled = !available;
  reason.minLength = 10;
  // Native maxlength counts UTF-16 units; the custom limit matches the server's Unicode character count.
  reason.maxLength = 2000;
  let saving = false;
  function updateCounter() {
    root.querySelector("#change-reason-count").textContent = studentChangeCharacterCount(reason.value) + " / 1,000";
    reason.setCustomValidity("");
    reason.removeAttribute("aria-invalid");
    error.textContent = "";
  }
  reason.addEventListener("input", updateCounter);
  updateCounter();
  const search = root.querySelector("#student-change-search"), status = root.querySelector("#student-change-status");
  search.value = studentChangeFilters.search;
  status.value = studentChangeFilters.status;
  function drawRequests() {
    studentChangeFilters.search = search.value;
    studentChangeFilters.status = status.value;
    const query = search.value.trim().toLocaleLowerCase();
    const visible = requests.filter(request => (!status.value || studentChangeStatus(request) === status.value) && [studentChangeRequestNumber(request, state.current_year), request.reason, studentChangeScheduleText(studentChangeCurrentSchedule(request, state)), studentChangeStatus(request), request.student_message, request.assigned_schedule ? studentChangeScheduleText(request.assigned_schedule) : ""].join(" ").toLocaleLowerCase().includes(query));
    root.querySelector("#student-change-body").innerHTML = studentChangeRowsHtml(visible, state);
    const empty = root.querySelector("#student-change-empty");
    empty.hidden = Boolean(visible.length);
    empty.querySelector("strong").textContent = requests.length ? "No matching requests" : "No requests submitted";
    empty.querySelector("p").textContent = requests.length ? "Try another search or clear your filters." : "Your submitted schedule change requests will appear here.";
    root.querySelector("#student-change-showing").textContent = "Showing " + visible.length + " of " + requests.length + " requests";
  }
  search.addEventListener("input", drawRequests);
  status.addEventListener("change", drawRequests);
  root.querySelector("#student-change-clear").addEventListener("click", () => {search.value = ""; status.value = ""; drawRequests(); search.focus();});
  drawRequests();

  let pageDialog, pageDialogCleanup;
  const priorCleanup = dashboardCleanup;
  dashboardCleanup = () => {if (pageDialog?.open) pageDialog.close(); pageDialogCleanup?.(); pageDialog?.remove(); priorCleanup();};
  function openRequestDialog(request, trigger, cancelling = false) {
    if (pageDialog?.open) pageDialog.close();
    pageDialogCleanup?.();
    pageDialog?.remove();
    const dialog = document.createElement("dialog");
    pageDialog = dialog;
    dialog.id = "student-change-dialog";
    dialog.className = "student-change-dialog";
    dialog.setAttribute("aria-labelledby", "student-change-dialog-title");
    const detailPairs = [["Request ID", studentChangeRequestNumber(request, state.current_year)], ["Date submitted", studentChangeDate(request.created_at, true)], ["Current schedule", studentChangeScheduleText(studentChangeCurrentSchedule(request, state))], ["Reason for request", request.reason], ["Status", studentChangeStatus(request)], ["CFO response", request.student_message || "Awaiting CFO review."], ["Assigned schedule", studentChangeScheduleText(request.assigned_schedule)]];
    dialog.innerHTML = `<header class="student-change-dialog-head"><h2 id="student-change-dialog-title">${cancelling ? "Cancel pending request?" : "Request Details"}</h2><button type="button" class="outline" data-student-change-close aria-label="Close request dialog">${icon("close")}</button></header>${cancelling ? '<p>Your current schedule will remain assigned. You can submit a new request after cancelling.</p>' : ""}<dl class="student-change-details">${detailPairs.map(([label, value]) => `<div${["Current schedule", "Reason for request", "CFO response", "Assigned schedule"].includes(label) ? ' class="full"' : ""}><dt>${escapeHTML(label)}</dt><dd>${escapeHTML(value)}</dd></div>`).join("")}</dl><div class="student-change-dialog-actions"><button type="button" class="outline" data-student-change-close>${cancelling ? "Keep request" : "Close"}</button>${cancelling ? '<form id="student-change-cancel-form"><button type="submit" class="primary student-change-cancel-confirm">Cancel Request</button></form>' : request.status === "Pending" ? `<button type="button" class="outline" data-student-change-dialog-cancel>Cancel Request</button>` : ""}</div>`;
    document.body.append(dialog);
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    let restored = false;
    const restoreDialog = () => {
      if (restored) return;
      restored = true;
      document.body.style.overflow = previousOverflow;
      if (trigger?.isConnected) trigger.focus();
    };
    pageDialogCleanup = restoreDialog;
    let cancelBusy = false;
    const close = () => {if (!cancelBusy) {dialog.close(); restoreDialog();}};
    dialog.addEventListener("click", event => {if (event.target === dialog || event.target.closest("[data-student-change-close]")) close();});
    dialog.addEventListener("cancel", event => {event.preventDefault(); close();});
    dialog.addEventListener("close", restoreDialog);
    dialog.querySelector("[data-student-change-dialog-cancel]")?.addEventListener("click", () => {dialog.close(); openRequestDialog(request, trigger, true);});
    const cancelForm = dialog.querySelector("#student-change-cancel-form");
    if (cancelForm) cancelForm.onsubmit = event => {
      event.preventDefault();
      if (cancelBusy || request.status !== "Pending") return;
      cfoSubmit(cancelForm, async () => {
        if (cancelBusy) return;
        cancelBusy = true;
        const button = cancelForm.querySelector("[type=submit]"), label = button.textContent;
        button.textContent = "Cancelling…";
        dialog.setAttribute("aria-busy", "true");
        try {
          if (backend) {
            const result = await cfoRequest("/api/change-requests/" + encodeURIComponent(request.id) + "/cancel", "PATCH");
            cfoStudent = {...cfoStudent, requests: cfoStudent.requests.map(item => String(item.id) === String(request.id) ? {...item, ...result.request, status: "Cancelled", student_status: "Cancelled"} : item)};
            try {cfoStudent = await cfoRequest("/api/student");} catch (refreshError) {
              if (typeof pendingStudentResources !== "undefined") pendingStudentResources.add("schedule_changes");
            }
          } else {request.status = "Cancelled"; request.student_status = "Cancelled"; request.student_message = "You cancelled this request.";}
          cancelBusy = false;
          dialog.close(); restoreDialog();
          if (!stillCurrentPage()) return;
          dashboardCleanup();
          renderStudentScheduleChange(backend ? cfoStudent : state, backend);
          root.isConnected || document.querySelector('[data-student-change-action="view"][data-id="' + request.id + '"]')?.focus();
          showNotice(backend ? "Request cancelled" : "Cancellation preview", backend ? "Your schedule change request was cancelled. Your current schedule remains assigned." : "Sample request cancelled in this UI preview only.");
        } finally {cancelBusy = false; button.textContent = label; dialog.removeAttribute("aria-busy");}
      }, true);
    };
    dialog.showModal();
    dialog.querySelector("[data-student-change-close]").focus();
  }
  root.addEventListener("click", event => {
    const button = event.target.closest("[data-student-change-action]");
    if (!button || !root.contains(button)) return;
    const request = requests.find(item => String(item.id) === button.dataset.id);
    if (!request) return;
    if (button.dataset.studentChangeAction === "view") openRequestDialog(request, button);
    if (button.dataset.studentChangeAction === "cancel" && request.status === "Pending") openRequestDialog(request, button, true);
  });
  form.onsubmit = event => {
    event.preventDefault();
    if (!available || saving) return;
    const reasonText = reason.value.trim();
    const characterCount = studentChangeCharacterCount(reasonText);
    const validation = characterCount < 10 ? "Enter a reason with at least 10 characters." : characterCount > 1000 ? "Keep your reason within 1,000 characters." : "";
    reason.setCustomValidity(validation);
    error.textContent = validation;
    if (validation) {reason.setAttribute("aria-invalid", "true"); reason.reportValidity(); reason.focus(); return;}
    cfoSubmit(form, async () => {
      if (saving) return;
      saving = true;
      const label = submit.textContent;
      submit.textContent = "Submitting…";
      form.setAttribute("aria-busy", "true");
      try {
        if (backend) {
          const created = await cfoRequest("/api/change-requests", "POST", {registration_id: registration.id, reason: reasonText});
          cfoStudent = {...cfoStudent, requests: [{...created, current_schedule: registration.schedule}, ...cfoStudent.requests]};
          try {cfoStudent = await cfoRequest("/api/student");} catch (refreshError) {
            if (typeof pendingStudentResources !== "undefined") pendingStudentResources.add("schedule_changes");
          }
        } else {
          state.requests.unshift({id: Math.max(0, ...requests.map(request => Number(request.id))) + 1, registration_id: registration.id, current_schedule_id: registration.recollection_schedule_id, current_schedule: registration.schedule, created_at: new Date().toISOString(), reason: reasonText, status: "Pending", student_message: "Your request is pending CFO approval. Your current schedule remains assigned."});
        }
        if (!stillCurrentPage()) return;
        dashboardCleanup(); renderStudentScheduleChange(backend ? cfoStudent : state, backend);
        showNotice(backend ? "Request submitted" : "Request preview", backend ? "Your request is awaiting CFO approval." : "Sample request submitted in this UI preview only.");
      } finally {saving = false; submit.textContent = label; form.removeAttribute("aria-busy");}
    }, false, [{label: "Current schedule", after: studentChangeScheduleText(registration.schedule)}, {label: "Reason for request", after: reasonText}]);
  };
}

/* 7.3.9 — Recollection history */
// Recollection History. Adapted from the supplied JavaScript styling and reference.
// The shared dashboard shell handles navigation, focus management, and the mobile drawer.


/* 7.3.10 — Certificate */
// Certificate UI adapted from the supplied code; shared navigation is reused.

// The supplied certificate artwork is embedded as a document preview asset.
// SVG viewport cropping shows only the certificate, not the surrounding application.
// Embedding the image keeps local downloads self-contained and works offline.
function studentCertificate() { connectedStudentCertificates(); }



/* 7.3.12 — Student routing and page lifecycle */
// Application entry. Shared helpers and page scripts load before this file.
let renderedRoute = null;
let routeVersion = 0;
let useLiveStudentState = false;
let studentRouteLoading = false;
async function route() {
  if (renderedRoute === location.hash) return;
  const liveRefresh = useLiveStudentState;
  if (window.CFO_BACKEND) {
    const version = ++routeVersion;
    const requestedRoute = location.hash;
    const publicRoute =
      !location.hash ||
      ["#login", "#forgot-password"].includes(location.hash) ||
      location.hash.startsWith("#register");
    if (!publicRoute) {
      if (!window.CFO_BACKEND.user) {
        location.href = "/login";
        return;
      }
      if (window.CFO_BACKEND.user.role === "Admin") {
        location.href = "/admin-panel.html";
        return;
      }
      if (location.hash.startsWith("#admin")) {
        location.hash = "dashboard";
        return;
      }
      try {
        if (useLiveStudentState) useLiveStudentState=false;
        else {
          studentRouteLoading = true;
          const fresh = await cfoRequest("/api/student");
          if (version !== routeVersion || requestedRoute !== location.hash) return;
          cfoStudent = fresh;
          if (fresh.user) window.CFO_BACKEND.user = fresh.user;
        }
      } catch (error) {
        if (version === routeVersion && requestedRoute === location.hash) {
          showNotice("Unable to load your account", error.message);
        }
        return;
      } finally {
        if (version === routeVersion) studentRouteLoading = false;
      }
      if (version !== routeVersion || requestedRoute !== location.hash) return;
    }
  }
  renderedRoute = location.hash;
  document.querySelectorAll("dialog[open]").forEach((dialog) => dialog.close());
  dashboardCleanup();

  const pageRoute = location.hash.split("?")[0];
  const isAdmin = false;
  document.body.classList.toggle("admin-mode", isAdmin);
  const isRecollection = pageRoute === "#recollection-registration";
  const isMyProfile = pageRoute === "#my-profile";
  const isCertificate = pageRoute === "#certificate";
  const isHistory = pageRoute === "#recollection-history";
  const isAnnouncements = pageRoute === "#announcements";
  const isDashboard = pageRoute === "#dashboard";
  const isScheduleChange = pageRoute === "#schedule-change-request";
  document.body.classList.toggle(
    "dashboard-mode",
    isDashboard || isRecollection || isScheduleChange || isHistory || isAnnouncements || isCertificate || isMyProfile,
  );
  document.body.classList.toggle("my-profile-mode", isMyProfile);
  document.body.classList.toggle("certificate-mode", isCertificate);
  document.body.classList.toggle("history-mode", isHistory);
  document.body.classList.toggle("recollection-mode", isRecollection);
  document.body.classList.toggle("schedule-change-mode", isScheduleChange);

  if (!isRecollection) clearRecollection();
  

  if (isMyProfile) {
    studentMyProfile();
  } else if (isCertificate) {
    studentCertificate();
  } else if (isHistory) {
    recollectionHistory();
  } else if (isAnnouncements) {
    studentAnnouncements();
  } else if (isScheduleChange) {
    scheduleChangeRequest();
  } else if (isRecollection) {
    recollectionRegistration();
  } else if (isDashboard) {
    dashboard();
    connectedRegistrationDetails();
    const focus = new URLSearchParams(location.hash.split('?')[1] || '').get('focus');
    const index = focus === 'attendance' ? 3 : focus === 'registration' ? 1 : -1;
    const status = app.querySelectorAll('.dash-status')[index];
    if(status){status.tabIndex=-1;status.dataset.notificationFocus='true';}
  } else if (location.hash.startsWith("#register")) {
    location.href="/login#register";
  } else if (location.hash === "#forgot-password") {
    location.href="/login#forgot-password";
  } else {
    location.hash="dashboard"; renderedRoute=null; route();
  }
  const heading = document.querySelector("#app [data-notification-focus], #announcement-focus") || document.querySelector("#app h1");
  if (heading && !liveRefresh) {
    heading.tabIndex = -1;
    heading.focus({ preventScroll: true });
  }
  if(!liveRefresh) {
    if(heading?.matches("[data-notification-focus], #announcement-focus"))heading.scrollIntoView({block:"center"});
    else window.scrollTo({ top: 0, behavior: "instant" });
  }
}

function connectedStudentCertificates() {
  dashboard({active:'certificate',titleId:'certificate-title',content:CfoStudentCertificates.pageHtml(cfoStudent)});
  const cleanup=CfoStudentCertificates.mount(document.querySelector('.certificate-page'),{state:cfoStudent,showNotice});
  const previousCleanup=dashboardCleanup;
  dashboardCleanup=()=>{cleanup();previousCleanup();};
}
function connectedRegistrationDetails() {
  const r=cfoStudent.registrations.find(r=>(!r.is_archived||r.can_reregister) && r.year===cfoStudent.current_year);
  if(!r)return;
  const note=document.querySelector('.dash-preview-note');
  if(note){note.textContent='';note.hidden=true;
    if(r.can_reregister)document.querySelector('.dash-welcome')?.insertAdjacentHTML('afterend',studentTemplate('html-dashboard-registration-notice'));
    if(r.status==='Approved'&&r.receipt_status==='Approved'&&!r.schedule){
      const available=cfoStudent.schedules.filter(schedule=>schedule.year===r.year);
      const picker=document.createElement('section');picker.className='panel';picker.style.cssText='margin-top:18px;padding:20px';
      const scheduleOptions=available.map(schedule=>'<option value="'+schedule.id+'">'+escapeHTML(schedule.title+' ? '+schedule.date+' ? '+schedule.time+' ? '+schedule.venue)+'</option>').join('');
      picker.innerHTML='<h2>Choose a Recollection Day</h2><p>Choose one available day for your course, year level and section.</p>'+(available.length?'<form id="choose-recollection-day"><label class="field">Available Recollection Day<select name="schedule_id" required>'+scheduleOptions+'</select></label><button type="submit" class="primary">Register for this day</button></form>':'<p>No available Recollection Days match your profile right now.</p>');
      note.insertAdjacentElement('afterend',picker);
      const form=document.querySelector('#choose-recollection-day');
      if(form)form.onsubmit=event=>{event.preventDefault();cfoSubmit(form,async()=>{const result=await cfoRequest('/api/registrations/'+r.id+'/schedule','POST',{schedule_id:Number(new FormData(form).get('schedule_id'))});cfoStudent=await cfoRequest('/api/student');renderedRoute=null;await route();showNotice('Recollection Day selected',result.message);});};
    }
    if(['Rejected','Not uploaded'].includes(r.receipt_status)){
      note.insertAdjacentHTML('afterend',`<form id="replace-receipt"><label>Upload receipt (JPG/PNG, up to 5 MB)<input type="file" name="receipt" accept="image/jpeg,image/png" required></label><button type="submit" class="primary">Submit receipt</button></form>`);
      document.querySelector('#replace-receipt').onsubmit=e=>{e.preventDefault();cfoSubmit(e.target,async()=>{await cfoRequest('/api/registrations/'+r.id+'/receipt','POST',new FormData(e.target));renderedRoute=null;await route();showNotice('Receipt saved','Your receipt is awaiting verification.');});};
    }
  }
}

let studentLiveVersions=null,studentLiveTimer=null,studentRefreshBusy=false;
const pendingStudentResources=new Set();
const isStudentEditing=()=>Boolean(document.activeElement?.matches?.('input,textarea,select'))||[...document.querySelectorAll('dialog')].some(dialog=>dialog.open)||Boolean(document.querySelector('form[data-dirty="true"]'))||Boolean(document.querySelector('#student-notification-panel:not([hidden])'))||Boolean(document.querySelector('.cfo-search-results:not([hidden])'));
for(const type of ['input','change'])document.addEventListener(type,event=>{const form=event.target.closest?.('form');if(form)form.dataset.dirty='true';});
async function pollStudentVersions(){
  if(window.CFO_STUDENT_LIVE_POLL_BUSY||document.hidden||!window.CFO_BACKEND?.user)return;
  window.CFO_STUDENT_LIVE_POLL_BUSY=true;
  try{
    const next=await cfoRequest('/api/live/versions');
    if(!studentLiveVersions){
      studentLiveVersions=next;
      // The first poll can arrive after midnight while the page still holds last year's state.
      if(cfoStudent && Number(next.year)!==Number(cfoStudent.current_year))pendingStudentResources.add('year');
      await refreshStudentWhenSafe();
      return;
    }
    Object.keys(next).filter(resource=>Number(next[resource])!==Number(studentLiveVersions[resource])).forEach(resource=>{
      pendingStudentResources.add(resource);
      if(['schedules','academic_options','users','year'].includes(resource))pendingStudentResources.add('registration_options');
    });
    studentLiveVersions=next;
    await refreshStudentWhenSafe();
  }catch(error){/* The next short poll reconnects automatically. */}
  finally{window.CFO_STUDENT_LIVE_POLL_BUSY=false;}
}
async function refreshStudentWhenSafe(){
  if(studentRefreshBusy||document.hidden||!pendingStudentResources.size)return;
  studentRefreshBusy=true;
  try{
    // Bell updates must continue while a form is dirty or the dropdown is open.
    if(pendingStudentResources.has('notifications')){
      pendingStudentResources.delete('notifications');
      try{
        const fresh=await cfoRequest('/api/student?resources=notifications');
        cfoStudent={...cfoStudent,notifications:fresh.notifications,notification_unviewed_count:fresh.notification_unviewed_count};
        updateStudentNotificationBell();
      }catch(error){pendingStudentResources.add('notifications');}
    }
    // Patch only the schedule-driven academic choices even while other form answers are unsaved.
    if(pendingStudentResources.has('registration_options')&&!studentRouteLoading){
      pendingStudentResources.delete('registration_options');
      const version=routeVersion,requestedRoute=location.hash;
      try{
        const fresh=await cfoRequest('/api/student?resources=registration_options');
        if(studentRouteLoading||version!==routeVersion||requestedRoute!==location.hash)pendingStudentResources.add('registration_options');
        else {
          cfoStudent={...cfoStudent,registration_schedules:fresh.registration_schedules||[],registration_courses:fresh.registration_courses||[],academic_options:fresh.academic_options||cfoStudent.academic_options,registration_course:fresh.registration_course??cfoStudent.registration_course};
          if(location.hash.split('?')[0]==='#recollection-registration')refreshRecollectionAcademicChoices();
        }
      }catch(error){pendingStudentResources.add('registration_options');}
    }
    if(isStudentEditing()||studentRouteLoading)return;
    const changed=[...pendingStudentResources].filter(resource=>!['notifications','registration_options'].includes(resource));
    if(!changed.length)return;
    changed.forEach(resource=>pendingStudentResources.delete(resource));
    const version=routeVersion, requestedRoute=location.hash;
    try{
      const fresh=await cfoRequest('/api/student?resources='+encodeURIComponent(changed.join(',')));
      if(isStudentEditing()||studentRouteLoading||version!==routeVersion||requestedRoute!==location.hash){changed.forEach(resource=>pendingStudentResources.add(resource));return;}
      cfoStudent={...cfoStudent,...fresh};
      if(fresh.user)window.CFO_BACKEND.user=fresh.user;
      useLiveStudentState=true;renderedRoute=null;await route();
    }catch(error){changed.forEach(resource=>pendingStudentResources.add(resource));}
  }finally{studentRefreshBusy=false;}
}
document.addEventListener('focusout',()=>setTimeout(refreshStudentWhenSafe,250));
document.addEventListener('close',()=>setTimeout(refreshStudentWhenSafe,250),true);
document.addEventListener('visibilitychange',()=>{if(document.hidden){if(studentLiveTimer){clearInterval(studentLiveTimer);studentLiveTimer=null;}}else{pollStudentVersions();if(!studentLiveTimer)studentLiveTimer=setInterval(pollStudentVersions,2500);}});
window.addEventListener("hashchange", route);
if (!location.hash) history.replaceState(null, "", "#dashboard");
if(window.CFO_BACKEND?.user){(async()=>{await pollStudentVersions();await route();if(!document.hidden)studentLiveTimer=setInterval(pollStudentVersions,2500);})();}else route();

// Preserve the existing optional browser navigation integration.
if (document.modelContext?.registerTool) {
  const lifecycle = new AbortController();
  const screens = ["login", "register", "forgot-password"];
  try {
    const toolRegistration = document.modelContext.registerTool(
      {
        name: "navigate_cfo_screen",
        title: "Open CFO screen",
        description:
          "Open the login, registration or password reset interface. This does not create an account or authenticate.",
        inputSchema: {
          type: "object",
          properties: { screen: { type: "string", enum: screens } },
          required: ["screen"],
          additionalProperties: false,
        },
        annotations: { readOnlyHint: false, untrustedContentHint: false },
        execute(input) {
          if (
            !input ||
            !screens.includes(input.screen) ||
            Object.keys(input).some((key) => key !== "screen")
          ) {
            throw new Error("Choose login, register or forgot-password.");
          }
          history.replaceState(null, "", `#${input.screen}`);
          route();
          return { screen: input.screen, prototype: true };
        },
      },
      { signal: lifecycle.signal },
    );
    Promise.resolve(toolRegistration).catch(() => {});
  } catch {
    // The UI also works in browsers without this optional integration.
  }
  window.addEventListener("pagehide", () => lifecycle.abort(), { once: true });
}

  </script>
@include('partials.history-details')
</body>
</html>
