<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#00543f">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="Christian Formation Office login, account creation, and password recovery.">
<title>Login • Create Account • Forgot Password | Christian Formation Office</title>
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
    background-position: -64.67px -17.33px;
    background-size: 1024px 682.67px;
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
  .dash-seal,
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
.recollection-course-row {
  grid-template-columns: 0.83fr 1.38fr 0.77fr;
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
  .recollection-field textarea {
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
/* Schedule Change Request screen. Other page styles remain unchanged. */
.schedule-change-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}
.schedule-change-mode .dash-content {
  max-width: 1600px;
  padding: 26px 24px 18px;
}
.schedule-change-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}
#schedule-change-root {
  min-width: 0;
  color: #17354b;
}
.schedule-change-heading {
  margin-bottom: 27px;
}
.schedule-change-heading h1 {
  font:
    700 39px/1.2 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #123e47;
}
.schedule-change-heading p {
  font-size: 18px;
  line-height: 1.5;
  color: #7a86a3;
  margin-top: 4px;
}
.schedule-change-panel {
  min-width: 0;
  padding: 25px 28px 24px;
  background: #ffffffeb;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.schedule-change-panel h2 {
  font:
    700 20px/1.35 Georgia,
    "Times New Roman",
    serif;
  margin: 0 0 8px;
}
.schedule-change-current {
  margin-bottom: 26px;
}
.schedule-change-current-card,
.schedule-change-status-card {
  display: flex;
  align-items: center;
  gap: 27px;
  min-width: 0;
  border-radius: 8px;
  background: linear-gradient(115deg, #e7f3ef, #ebf6f3);
  padding: 20px 28px;
}
.schedule-change-current-card {
  min-height: 115px;
}
.schedule-change-current-card > .icon {
  width: 40px;
  height: 40px;
  color: #006047;
  stroke-width: 2.6;
}
.schedule-change-current-card > div {
  min-width: 0;
}
.schedule-change-current-card strong {
  display: block;
  font-size: 21px;
  line-height: 1.35;
  color: #12443f;
  margin-bottom: 3px;
}
.schedule-change-current-card p {
  font-size: 17px;
  line-height: 1.4;
  color: #7286a6;
}
.schedule-change-field {
  min-width: 0;
  margin-bottom: 24px;
}
.schedule-change-field label {
  display: block;
  font-size: 17px;
  line-height: 1.4;
  font-weight: 650;
  margin-bottom: 7px;
}
.schedule-change-select {
  position: relative;
  min-width: 0;
}
.schedule-change-select select,
.schedule-change-field textarea {
  display: block;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  font: inherit;
  font-size: 17px;
  color: #19344b;
  background: #fcfcff;
  border: 1px solid #c8d1e1;
  border-radius: 7px;
}
.schedule-change-select select {
  min-height: 52px;
  padding: 12px 49px 12px 69px;
  appearance: none;
  text-overflow: ellipsis;
}
.schedule-change-select select:has(option:checked[value=""]) {
  color: #7b87a3;
}
.schedule-change-select > .icon {
  position: absolute;
  width: 23px;
  height: 23px;
  left: 24px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}
.schedule-change-select > .schedule-change-chevron {
  width: 18px;
  height: 18px;
  left: auto;
  right: 20px;
}
.schedule-change-field textarea {
  min-height: 134px;
  resize: vertical;
  padding: 13px 18px;
  line-height: 1.5;
}
.schedule-change-field textarea::placeholder {
  color: #7b87a3;
  opacity: 1;
}
.schedule-change-field textarea:focus {
  outline: none;
  border-color: #27846b;
  box-shadow: 0 0 0 3px #27846b18;
}
.schedule-change-field textarea[aria-invalid="true"] {
  border-color: #ac3232;
}
.schedule-change-submit {
  min-height: 55px;
  font-size: 18px;
  font-weight: 600 !important;
  gap: 15px;
}
.schedule-change-submit > .icon {
  width: 21px;
  height: 21px;
}
.schedule-change-status {
  margin-top: 14px;
  padding-top: 21px;
  padding-bottom: 23px;
}
.schedule-change-status-card {
  min-height: 87px;
  padding: 14px 19px;
  gap: 25px;
  background: linear-gradient(115deg, #edf7f3, #eef8f6);
}
.schedule-change-clock {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 57px;
  height: 57px;
  border-radius: 50%;
  color: #aa7b00;
  background: #ffecac;
}
.schedule-change-clock > .icon {
  width: 29px;
  height: 29px;
  stroke-width: 2.3;
}
.schedule-change-status-card strong {
  color: #b78300;
  font-size: 22px;
  font-weight: 650;
}
.schedule-change-note {
  text-align: center;
  margin-top: 14px;
  color: #7b8a9b;
  font-size: 11px;
  line-height: 1.5;
}
@media (max-width: 1100px) {
  .schedule-change-mode .dash-content {
    padding: 24px;
  }
  .schedule-change-heading h1 {
    font-size: 34px;
  }
  .schedule-change-heading p {
    font-size: 16px;
  }
}
@media (max-width: 600px) {
  .schedule-change-mode .dash-content {
    padding: 22px 14px 16px;
  }
  .schedule-change-heading {
    margin-bottom: 20px;
  }
  .schedule-change-heading h1 {
    font-size: 30px;
    letter-spacing: -0.5px;
  }
  .schedule-change-heading p {
    font-size: 15px;
    margin-top: 7px;
  }
  .schedule-change-panel {
    padding: 20px 16px;
  }
  .schedule-change-panel h2 {
    font-size: 19px;
  }
  .schedule-change-current {
    margin-bottom: 22px;
  }
  .schedule-change-current-card {
    padding: 18px 15px;
    gap: 16px;
    min-height: 106px;
  }
  .schedule-change-current-card > .icon {
    width: 31px;
    height: 31px;
  }
  .schedule-change-current-card strong {
    font-size: 18px;
  }
  .schedule-change-current-card p {
    font-size: 15px;
  }
  .schedule-change-field {
    margin-bottom: 20px;
  }
  .schedule-change-field label {
    font-size: 15px;
  }
  .schedule-change-select select,
  .schedule-change-field textarea {
    font-size: 16px;
  }
  .schedule-change-select select {
    padding-left: 45px;
    padding-right: 35px;
  }
  .schedule-change-select > .icon {
    left: 14px;
    width: 20px;
    height: 20px;
  }
  .schedule-change-select > .schedule-change-chevron {
    left: auto;
    right: 12px;
    width: 15px;
  }
  .schedule-change-field textarea {
    min-height: 140px;
    padding: 12px;
  }
  .schedule-change-submit {
    font-size: 16px;
    padding: 12px;
  }
  .schedule-change-status-card {
    gap: 16px;
    padding: 14px;
    min-height: 78px;
  }
  .schedule-change-clock {
    width: 46px;
    height: 46px;
  }
  .schedule-change-clock > .icon {
    width: 25px;
    height: 25px;
  }
  .schedule-change-status-card strong {
    font-size: 20px;
  }
}

/* 7.2.7 — history */
.history-mode {
  background: radial-gradient(ellipse at top right, #f9fcff, transparent 70%), #f1f7fa;
}
.history-mode .dash-content {
  max-width: 1600px;
  padding: 26px 24px;
}
.history-mode .dash-brand-subtitle {
  font-family: "Segoe UI", Arial, sans-serif;
  font-size: 17px;
}
#history-root {
  min-width: 0;
  color: #173b45;
}
.history-heading {
  margin-bottom: 27px;
}
.history-heading h1 {
  font:
    700 39px/1.2 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #123e47;
  margin: 0;
}
.history-heading p {
  font-size: 18px;
  line-height: 1.5;
  color: #7a86a3;
  margin: 4px 0 0;
}
.history-card {
  width: 100%;
  min-width: 0;
  padding: 21px;
  background: #ffffffeb;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.history-table-container {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid #edf1f4;
  border-radius: 9px;
  overscroll-behavior-x: contain;
}
.history-table-container:focus-visible {
  outline: 3px solid #27846b;
  outline-offset: 3px;
}
.history-table {
  width: 100%;
  min-width: 600px;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
  text-align: left;
}
.history-table th,
.history-table td {
  padding: 16px 23px;
  font-size: 16px;
  line-height: 1.5;
  vertical-align: middle;
  border-right: 1px solid #edf1f4;
  overflow-wrap: break-word;
}
.history-table th {
  background: linear-gradient(115deg, #e7f3ef, #edf6f5);
  color: #173b45;
  font-weight: 650;
}
.history-table td {
  color: #354d6d;
  border-top: 1px solid #edf1f4;
  background: #fcfdff;
}
.history-table th:last-child,
.history-table td:last-child {
  border-right: 0;
}
.history-table th:nth-child(1) {
  width: 24%;
}
.history-table th:nth-child(2) {
  width: 28%;
}
.history-table th:nth-child(3) {
  width: 27%;
}
.history-table th:nth-child(4) {
  width: 21%;
}
.history-completed {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 5px 17px;
  border-radius: 24px;
  background: #ccecd9;
  color: #137346;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}
@media (max-width: 1100px) {
  .history-mode .dash-content {
    padding: 24px 20px;
  }
  .history-heading h1 {
    font-size: 34px;
  }
  .history-heading p {
    font-size: 16px;
  }
  .history-table th,
  .history-table td {
    padding: 14px 16px;
    font-size: 14px;
  }
  .history-completed {
    font-size: 13px;
    padding: 5px 12px;
  }
}
@media (max-width: 600px) {
  .history-mode .dash-content {
    padding: 22px 14px;
  }
  .history-heading {
    margin-bottom: 20px;
  }
  .history-heading h1 {
    font-size: 29px;
    letter-spacing: -0.4px;
  }
  .history-heading p {
    font-size: 15px;
  }
  .history-card {
    padding: 10px;
    border-radius: 10px;
  }
  .history-table th,
  .history-table td {
    padding: 13px 14px;
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
.my-profile-heading {
  margin-bottom: 23px;
}
.my-profile-heading h1 {
  font:
    700 39px/1.2 Georgia,
    "Times New Roman",
    serif;
  letter-spacing: -0.7px;
  color: #123e47;
  margin: 0;
}
.my-profile-heading p {
  font-size: 18px;
  line-height: 1.5;
  color: #7a86a3;
  margin: 4px 0 0;
}
.my-profile-card {
  width: 100%;
  min-width: 0;
  padding: 25px 28px 28px;
  background: #ffffffeb;
  border: 1px solid #e8edf3;
  border-radius: 10px;
  box-shadow: 0 3px 15px #1f4e5e08;
}
.my-profile-field {
  min-width: 0;
  margin-bottom: 26px;
}
.my-profile-field label {
  display: block;
  margin-bottom: 6px;
  color: #203858;
  font-size: 16px;
  line-height: 1.4;
  font-weight: 650;
}
.my-profile-field input {
  display: block;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  min-height: 49px;
  padding: 10px 16px;
  border: 1px solid #c8d1e1;
  border-radius: 7px;
  background: #fcfcff;
  color: #304967;
  font-size: 16px;
  line-height: 1.4;
}
.my-profile-field input:read-only {
  background: #f1f3f7;
  color: #7986a0;
}
.my-profile-field input:focus {
  outline: none;
  border-color: #27846b;
  box-shadow: 0 0 0 3px #27846b18;
}
.my-profile-field input::placeholder {
  color: #304967;
  opacity: 1;
  letter-spacing: 2px;
}
.my-profile-submit {
  width: 100%;
  min-height: 54px;
  padding: 12px;
  gap: 16px;
  font-size: 17px !important;
  font-weight: 600 !important;
}
.my-profile-submit .icon {
  width: 23px;
  height: 23px;
  flex-shrink: 0;
}
.my-profile-note {
  font-size: 12px;
  line-height: 1.5;
  color: #738491;
  margin: 14px 0 0;
}
@media (max-width: 1100px) {
  .my-profile-mode .dash-content {
    padding: 24px 20px;
  }
  .my-profile-heading h1 {
    font-size: 34px;
  }
  .my-profile-heading p {
    font-size: 16px;
  }
}
@media (max-width: 600px) {
  .my-profile-mode .dash-content {
    padding: 22px 14px;
  }
  .my-profile-heading {
    margin-bottom: 20px;
  }
  .my-profile-heading h1 {
    font-size: 29px;
    letter-spacing: -0.4px;
  }
  .my-profile-heading p {
    font-size: 15px;
  }
  .my-profile-card {
    padding: 20px 16px;
  }
  .my-profile-field {
    margin-bottom: 22px;
  }
  .my-profile-field label {
    font-size: 15px;
  }
  .my-profile-field input {
    padding: 11px 12px;
  }
  .my-profile-submit {
    font-size: 16px !important;
  }
}

  
</style>
@include('partials.cfo-usability')
<link rel="stylesheet" href="{{ asset('login-background.css') }}?v={{ filemtime(public_path('login-background.css')) }}">
<link rel="stylesheet" href="{{ asset('recovery.css') }}?v={{ filemtime(public_path('recovery.css')) }}">
<link rel="stylesheet" href="{{ asset('auth-branding.css') }}?v={{ filemtime(public_path('auth-branding.css')) }}">
<link rel="stylesheet" href="{{ asset('auth-pages.css') }}?v={{ filemtime(public_path('auth-pages.css')) }}">
</head>
<body>
<div class="campus campus-left" aria-hidden="true"></div>
<div class="campus campus-right" aria-hidden="true"></div>
<main id="app"></main>
<dialog id="notice"><div class="dialog-icon" data-icon="info"></div><h2 id="notice-title"></h2><p id="notice-text"></p><button class="primary" id="notice-close" type="button">Got it</button></dialog>
<script src="/recovery-polling.js?v={{ filemtime(public_path('recovery-polling.js')) }}"></script>
<script src="{{ asset('cfo-account-ids.js') }}?v={{ filemtime(public_path('cfo-account-ids.js')) }}"></script>
<script src="{{ asset('cfo-registration-email.js') }}?v={{ filemtime(public_path('cfo-registration-email.js')) }}"></script>
<script>
window.CFO_BACKEND = {{ Illuminate\Support\Js::from($cfoBackend) }};
if (window.CFO_BACKEND && !window.CFO_BACKEND.csrf_token) {
  window.CFO_BACKEND.csrf_token = document.querySelector('meta[name="csrf-token"]').content;
}
const iconPaths = {"user": "M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8", "person": "M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8", "chevron": "m9 18 6-6-6-6", "down": "m6 9 6 6 6-6", "eye": "M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0", "lock": "M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4", "login": "M10 17l5-5-5-5M15 12H3M21 3v18", "info": "M12 17v-5M12 7h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0", "cap": "M2 8l10-5 10 5-10 5-10-5M5 10v7c4 4 10 4 14 0v-7", "id": "M2 5h20v14H2zM7 10h4M7 14h7", "check": "m5 12 4 4L19 6", "left": "M19 12H5m7 7-7-7 7-7", "right": "M5 12h14m-7-7 7 7-7 7", "copy": "M9 3h11v14H9zM6 7H3v14h11v-2", "edit": "m4 16 12-12 4 4L8 20H4zM14 6l4 4", "shield": "M12 2l9 4v6c0 5-5 9-9 11-4-2-9-6-9-11V6z", "send": "M22 2 9 15M22 2l-7 20-6-7-7-6"};
function icon(name, cls=""){return `<svg class="icon ${cls}" viewBox="0 0 24 24" aria-hidden="true"><path d="${iconPaths[name]||iconPaths.info}"/></svg>`;}
function registerStyles(id, css){let node=document.getElementById(`style-${id}`);if(!node){node=document.createElement("style");node.id=`style-${id}`;document.head.appendChild(node);}node.textContent=css;}
// Shared fields, notices, and password validation. Loaded before page scripts.

// Mount the shared UI shell before rendering a page.
document.body.insertAdjacentHTML('afterbegin', `<div class="campus campus-left" aria-hidden="true"></div>
  <div class="campus campus-right" aria-hidden="true"></div>
  <main id="app"></main>
  <dialog id="notice"><div class="dialog-icon" data-icon="info"></div><h2 id="notice-title"></h2><p id="notice-text"></p><button class="primary" id="notice-close" type="button">Got it</button></dialog>`);

const app=document.querySelector('#app');

const escapeHTML=(value)=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function field(id,label,placeholder,type='text',symbol='person',value='',required=true){return `<div class="field"><label for="${id}">${label}</label><div class="input-wrap ${type==='password'?'password-input':''}">${icon(symbol)}<input id="${id}" name="${id}" type="${type}" placeholder="${placeholder}" value="${escapeHTML(value)}" ${required?'required':''} ${type==='password'?'autocomplete="new-password"':type==='email'?'autocomplete="email"':''}>${type==='password'?`<button type="button" class="reveal" aria-label="Show ${label.toLowerCase()}" data-reveal="${id}">${icon('eye')}</button>`:''}</div></div>`}

function roleSelect(id='role',login=false){return `<div class="field ${login?'':'role-field'}"><label for="${id}">${login?'Role':'Select Role'}</label><div class="input-wrap">${icon('user')}<select id="${id}" name="role" required>${login?'<option value="">Select Role</option>':''}<option ${!login&&accountState.role==='Student'?'selected':''}>Student</option><option ${!login&&accountState.role==='Admin'?'selected':''}>Admin</option></select>${icon('down','select-chevron')}</div>${login?'<small>Please select whether you are a Student or an Admin.</small>':''}</div>`}

function showNotice(title,text){document.querySelector('#notice-title').textContent=title;document.querySelector('#notice-text').textContent=text;document.querySelector('.dialog-icon').innerHTML=icon('info');document.querySelector('#notice').showModal()}

document.querySelector('#notice-close').addEventListener('click',()=>document.querySelector('#notice').close());

function bindReveal(){document.querySelectorAll('[data-reveal]').forEach(button=>button.onclick=()=>{const input=document.getElementById(button.dataset.reveal);const showing=input.type==='password';input.type=showing?'text':'password';button.setAttribute('aria-label',`${showing?'Hide':'Show'} ${document.querySelector(`label[for="${input.id}"]`).textContent.toLowerCase()}`);button.setAttribute('aria-pressed',String(showing))})}

function banner(symbol,title,text){return `<div class="banner">${icon(symbol)}<div><strong>${title}</strong><p>${text}</p></div></div>`}

const rules=[{text:'At least 8 characters',test:v=>v.length>=8},{text:'At least one uppercase letter (A–Z)',test:v=>/[A-Z]/.test(v)},{text:'At least one lowercase letter (a–z)',test:v=>/[a-z]/.test(v)},{text:'At least one number (0–9)',test:v=>/[0-9]/.test(v)},{text:'At least one special character (e.g. ! @ # $ % ^ & *)',test:v=>/[^a-zA-Z0-9\s]/.test(v)}];


// Shared logout confirmation, mounted once for every student screen.
registerStyles('logout-modal', `
#logoutOverlay{width:min(440px,calc(100% - 32px));max-width:440px;max-height:calc(100dvh - 32px);padding:0;border:1px solid #e1eae6;border-radius:16px;background:#fff;color:#173b45;box-shadow:0 20px 60px #002c3540;text-align:left;overflow:auto}
#logoutOverlay::backdrop{background:#062f3699}
.logout-modal-content{position:relative;padding:30px}
#closeLogout{position:absolute;top:12px;right:12px;width:40px;height:40px;display:grid;place-items:center;background:transparent;border-radius:8px;color:#506a65;font-size:26px}
#closeLogout:hover{background:#edf5f1}
.logout-modal-icon{width:52px;height:52px;display:grid;place-items:center;background:#e7f3ed;color:#075a43;border-radius:50%;margin-bottom:18px}
#logoutTitle{font:700 27px/1.2 Georgia,serif;margin:0 36px 12px 0;color:#123e47}
#logoutDescription{font-size:16px;line-height:1.6;color:#64748a;margin:0}
.logout-modal-actions{display:flex;gap:12px;margin-top:26px}
.logout-modal-actions button{flex:1;min-width:0;min-height:46px;padding:10px 16px;border-radius:8px;font-size:16px;font-weight:600}
#cancelLogout{background:#fff;border:1px solid #c9d6d2;color:#245448}
body.logout-modal-open{overflow:hidden}
@media(max-width:400px){.logout-modal-content{padding:24px 20px}.logout-modal-actions{gap:10px}}
`);
document.body.insertAdjacentHTML('beforeend', `<dialog id="logoutOverlay" aria-labelledby="logoutTitle" aria-describedby="logoutDescription">
  <div class="logout-modal-content">
    <button type="button" id="closeLogout" aria-label="Close logout confirmation">×</button>
    <div class="logout-modal-icon" aria-hidden="true">${icon('login')}</div>
    <h2 id="logoutTitle">Confirm Logout</h2>
    <p id="logoutDescription">Are you sure you want to log out? You will return to the login page.</p>
    <div class="logout-modal-actions"><button type="button" id="cancelLogout" autofocus>Cancel</button><button type="button" id="confirmLogout" class="primary">Logout</button></div>
  </div>
</dialog>`);
const logoutOverlay = document.getElementById('logoutOverlay');
function openLogoutModal(returnFocusTo) {
  if (logoutOverlay.open) return;
  // Focus a visible trigger so the native dialog can restore focus on dismissal.
  returnFocusTo?.focus({preventScroll:true});
  logoutOverlay.showModal();
  document.body.classList.add('logout-modal-open');
  document.getElementById('cancelLogout').focus();
}
function closeLogoutModal() {
  if (logoutOverlay.open) logoutOverlay.close();
  document.body.classList.remove('logout-modal-open');
}
document.getElementById('closeLogout').addEventListener('click', closeLogoutModal);
document.getElementById('cancelLogout').addEventListener('click', closeLogoutModal);
logoutOverlay.addEventListener('close', () => document.body.classList.remove('logout-modal-open'));
logoutOverlay.addEventListener('cancel', event => {event.preventDefault();closeLogoutModal();});
let logoutBackdropPressed = false;
function outsideLogout(event) {
  const bounds=logoutOverlay.getBoundingClientRect();
  return event.target===logoutOverlay && (event.clientX<bounds.left || event.clientX>bounds.right || event.clientY<bounds.top || event.clientY>bounds.bottom);
}
logoutOverlay.addEventListener('pointerdown',event=>{logoutBackdropPressed=outsideLogout(event);});
logoutOverlay.addEventListener('click',event=>{if(logoutBackdropPressed && outsideLogout(event))closeLogoutModal();logoutBackdropPressed=false;});
document.getElementById('confirmLogout').addEventListener('click',async()=>{
  if (window.CFO_BACKEND) {
    try { await cfoRequest('/api/logout', 'POST'); location.href='/#login'; }
    catch (error) { closeLogoutModal(); showNotice('Logout failed', error.message); }
    return;
  }
  closeLogoutModal();
  // UI-only navigation; no server session or authentication service is connected.
  location.hash='login';
});

// Same-origin Laravel sessions are enabled only by the Laravel page controller.
let cfoStudent = { schedules: [], registrations: [], requests: [] };
async function cfoRequest(url, method = 'GET', data) {
  const multipart = data instanceof FormData;
  const response = await fetch(url, {
    method, credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': window.CFO_BACKEND.csrf_token,
      ...(data && !multipart ? {'Content-Type':'application/json'} : {}) },
    ...(data ? {body: multipart ? data : JSON.stringify(data)} : {})
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 401) location.href = '/#login';
    const error = new Error(response.status === 419 ? 'Your session expired. Reload the page and try again.' : Object.values(result.errors || {}).flat().join('\n') || result.message || 'The request could not be completed.');
    error.recoveryLimit = result.recovery_limit;
    throw error;
  }
  if (result.csrf_token) Object.assign(window.CFO_BACKEND, result);
  return result;
}
async function cfoSubmit(form, action, skipConfirmation = false) {
  if(!skipConfirmation&&form.id!=='login-form'){let dialog=document.querySelector('#action-confirm');if(!dialog){dialog=document.createElement('dialog');dialog.id='action-confirm';dialog.style.cssText='width:min(440px,calc(100% - 32px));padding:24px';dialog.className='auth-confirm-dialog';dialog.innerHTML='<h2>Confirm before saving</h2><p id="action-confirm-text"></p><div class="auth-confirm-actions"><button type="button" class="outline" id="action-confirm-cancel">Cancel</button><button type="button" class="primary" id="action-confirm-accept">Confirm</button></div>';document.body.append(dialog);}const label=form.querySelector('[type=submit]')?.textContent.trim()||'save your changes';dialog.querySelector('#action-confirm-text').textContent='This will '+label.toLowerCase()+'. You can review the details before continuing.';dialog.showModal();dialog.querySelector('#action-confirm-cancel').onclick=()=>dialog.close();dialog.querySelector('#action-confirm-accept').onclick=()=>{dialog.close();cfoSubmit(form,action,true);};return;}
  const button = form.querySelector('[type=submit]');
  if (button?.disabled) return;
  if (button) button.disabled = true;
  form.dataset.submitting = 'true';
  try { await action(); }
  catch (error) { showNotice('Please check your request', error.message); }
  finally { delete form.dataset.submitting; if (button) button.disabled = false; if (form.id === 'recovery-form') syncRecoveryRequestLimit(); }
}

// Consistent button contrast across the existing student and admin interfaces.
registerStyles('button-contrast', `
body .primary{background:#07543f;color:#fff;border:1px solid #064332;font-weight:600}
body .outline{background:#fff;color:#153e35;border:1px solid #557a70;font-weight:600}
body .reveal,body .copy-button{color:#244b41}
body .dash-shell .dash-nav a,body .dash-shell .dash-nav button,body .dash-shell .dash-logout,
body .admin-ui .nav-item,body .admin-ui .nav-item:nth-of-type(5),body .admin-ui .logout-link{color:#fff;font-weight:600}
body .dash-shell .dash-nav .is-active{background:#286456;box-shadow:inset 3px 0 #c0efdf}
body .admin-ui .nav-item.active{background:#064bd8;box-shadow:inset 3px 0 #baceff}
body .dash-shell .dash-nav a:hover,body .dash-shell .dash-nav button:hover,body .dash-shell .dash-logout:hover{background:#286456}
body .admin-ui .nav-item:hover,body .admin-ui .logout-link:hover{background:#203b69}
body .dash-shell .dash-icon-button:not(.dash-sidebar-close),body .admin-ui #admin-menu,
body .admin-ui .notification{color:#123d39;background:#edf3f4;border:1px solid #6c8583;border-radius:8px}
body .dash-shell .dash-sidebar-close,body .admin-ui .admin-close{color:white;background:#ffffff18;border:1px solid #a5bbb9;border-radius:8px}
body .dash-shell .dash-quick-grid button{border:1px solid var(--tone);font-weight:600}
body .dash-shell .dash-panel-heading>button{color:#07543f;font-weight:600;text-decoration:underline;text-underline-offset:3px}
body .dash-shell .dash-profile-menu button{color:#123d39;font-weight:600}
body .admin-ui .view-btn,body .admin-ui .view-all,body .admin-ui .date-selector,
body .admin-ui .students-button,body .student-dialog button{color:#143e9c;border:1px solid #5075ad;background:#f4f7ff;font-weight:600}
body .admin-ui .stat-link{color:#0644b8;font-weight:600;text-decoration:underline;text-underline-offset:3px}
body .admin-ui .students-primary,body .student-dialog .students-primary{background:#1249bd;color:#fff;border-color:#103c97}
body .admin-ui .students-danger,body .student-dialog .students-danger{color:#a51d30;background:#fff2f3;border-color:#b63749}
body .admin-ui .students-button:disabled{opacity:1;color:#596579;background:#e5e9f0;border-color:#8994a6;cursor:not-allowed}
body #recollection-root .recollection-receipt-actions button{color:#07543f;border-color:#397763;font-weight:600}
body #recollection-root .recollection-receipt-actions #receipt-remove{color:#a51d30;border-color:#b63749;background:#fff2f3}
body #cancelLogout{color:#153e35;border-color:#557a70;background:#f4f8f6}
body #closeLogout{color:#153e35;border:1px solid #6c8583;background:#f4f8f6}
body #confirmLogout{color:#fff;background:#a92232;border-color:#911b29}
body button:focus-visible,body a:focus-visible{outline:3px solid #176aa1;outline-offset:3px}
body .dash-sidebar button:focus-visible,body .dash-sidebar a:focus-visible,
body .admin-ui .sidebar button:focus-visible,body .admin-ui .sidebar a:focus-visible{outline:2px solid #fff;outline-offset:-3px}
`);


// Login screen and its UI-only actions.
function login() {
  document.body.classList.remove('registration-mode');
  app.innerHTML = `
    <section class="login-page">
      <header class="login-heading">
        <h1>Christian Formation Office</h1>
        <p>Welcome! Please login to continue.</p>
      </header>
      <div class="quick-actions">
        <a class="quick-card" href="#register">
          ${icon('user')}
          <div><strong>Create Account</strong><span>Create a new student<br>or admin account.</span></div>
          ${icon('chevron', 'chevron')}
        </a>
        <a class="quick-card secondary" href="#forgot-password">
          ${icon('lock')}
          <div><strong>Forgot Password?</strong><span>Reset your password.</span></div>
          ${icon('chevron', 'chevron')}
        </a>
      </div>
      <section class="login-card">
        <h2 class="login-title"><span class="title-icon">${icon('user')}</span>Login</h2>
        <p class="login-description">Enter your credentials to access your account.</p>
        <form id="login-form">
          ${roleSelect('login-role', true)}
          ${field('login-email', 'Email Address', 'Enter your email address', 'email', 'mail')}
          ${field('login-password', 'Password', 'Enter your password', 'password', 'lock')}
          <label class="remember"><input type="checkbox" id="remember">Remember me</label>
          <button class="primary full" type="submit">${icon('login')}Login</button>
        </form>
        <div class="divider">or</div>
        <button class="primary full google" id="google-login" type="button">
          <span class="google-mark" aria-hidden="true">G</span>Continue with Google
        </button>
      </section>
    </section>`;

  document.querySelector('#login-password').autocomplete = 'current-password';
  if (window.CFO_BACKEND) document.querySelector('.quick-card span').textContent = 'Create a new student account.';
  document.querySelector('#login-form').onsubmit = event => {
    event.preventDefault();
    if (window.CFO_BACKEND) {
      cfoSubmit(event.target, async () => {
        const result = await cfoRequest('/api/login', 'POST', {
          email: document.querySelector('#login-email').value,
          password: document.querySelector('#login-password').value,
          role: document.querySelector('#login-role').value,
          remember: document.querySelector('#remember').checked
        });
        document.querySelector('#login-password').value = '';
        location.href = result.redirect;
      });
      return;
    }
    showNotice('Login preview', 'This interface is ready for connection to the CFO login service. No credentials have been sent or saved.');
    event.target.reset();
  };
  document.querySelector('#google-login').disabled = true; document.querySelector('#google-login').textContent = 'Google sign-in is not configured';
  bindReveal();
}


// Account creation: information, password, and review screens.

const accountState={step:1,role:'Student',studentId:'',firstName:'',middleName:'',lastName:'',email:'',password:'',confirmPassword:''};

function reminder(blue=false){return `<aside class="reminder ${blue?'blue':''}">${icon('info')}<div><strong>Important Reminder</strong>${blue?'After creating your account, visit the Christian Formation Office in person for account verification and approval before you can log in to the system.':'After completing the registration, your account will be subject for approval.<br>Visit the Christian Formation Office in person for verification and approval before you can log in to the system.'}</div></aside>`}

function actions(backLabel='Back',nextLabel='Next',backSymbol='left',nextSymbol='right'){return `<p class="error" id="form-error" role="alert"></p><div class="form-actions"><button class="outline" type="button" id="previous">${icon(backSymbol)}${backLabel}</button><button class="primary" type="submit">${nextLabel==='Create Account'?icon('check'):''}${nextLabel}${nextLabel==='Next'?icon(nextSymbol):''}</button></div>`}

function detail(label,value){return `<div class="detail-row"><dt>${label}</dt><dd>${escapeHTML(value||'—')}</dd></div>`}

function registration(){
 document.body.classList.add('registration-mode');
 const labels=['Account Information','Create Password','Review & Confirm'];
 let content='';
 if(accountState.step===1){content=`${banner('cap',`${accountState.role} Account Registration`,`Fill in your information below to create your ${accountState.role.toLowerCase()} account.`)}${roleSelect()}${field('studentId',accountState.role==='Admin'?'Employee ID Number':'Student ID Number','24-10723','text','id',accountState.studentId)}<div class="name-row">${field('firstName','First Name','Enter your first name','text','person',accountState.firstName)}${field('middleName','Middle Name','Enter your middle name','text','person',accountState.middleName,false)}${field('lastName','Last Name','Enter your last name','text','person',accountState.lastName)}</div>${field('email','Registered Gmail','Enter your Gmail address (e.g. juan@gmail.com)','email','mail',accountState.email)}${accountState.role==='Student'?`<p class="notice neutral" id="login-email-preview">Generated login email: <strong>${escapeHTML(accountState.email.includes('@')?accountState.email.trim().toLowerCase().split('@')[0]+'@cfo.office.com':'Enter a Gmail address')}</strong></p>`:''}${actions('Cancel','Next','close')}${reminder()}`}
 else if(accountState.step===2){content=`${banner('lock','Create a Secure Password','Your password helps keep your account safe. Please follow the requirements below.')}${field('password','Create Password','Enter your password','password','lock',accountState.password)}${field('confirmPassword','Confirm Password','Re-enter your password','password','lock',accountState.confirmPassword)}<aside class="requirements">${icon('shield')}<div><h2>Password Requirements</h2><ul>${rules.map((rule,i)=>`<li id="rule-${i}"><span class="rule-dot" aria-hidden="true"></span><span>${rule.text}<span class="rule-status sr-only"></span></span></li>`).join('')}</ul></div></aside>${actions()}`}
 else{content=`${banner('file','Review Your Information','Please check if all the information below is correct before creating your account.')}<div class="review-heading"><h2>Account Details</h2><button class="outline edit-button" type="button" data-edit="1" aria-label="Edit account details">${icon('edit')}Edit</button></div><dl class="details">${detail('Role',accountState.role)}${detail(accountState.role==='Admin'?'Employee ID Number':'Student ID Number',accountState.studentId)}${detail('First Name',accountState.firstName)}${detail('Middle Name',accountState.middleName)}${detail('Last Name',accountState.lastName)}${detail('Email Address',accountState.email)}</dl><div class="review-heading"><h2>Security Details</h2><button class="outline edit-button" type="button" data-edit="2" aria-label="Edit password">${icon('edit')}Edit</button></div><dl class="details">${detail('Password','••••••••••••')}</dl>${reminder(true)}${actions('Back','Create Account')}`}
 app.innerHTML=`<section class="registration-shell"><aside class="brand-panel auth-brand-panel" aria-label="Christian Formation Office Recollection"><img class="auth-seal" src="{{ asset('assets/ulslogo.png') }}" alt="Christian Formation Office logo" width="180" height="180"><p class="auth-university">Christian<br><strong>Formation Office</strong></p><p class="auth-product">Recollection</p><p class="auth-quote">In faith, in community,<br>towards a better you.</p><p class="auth-motto">A Journey of Faith<br>A Brighter Tomorrow</p></aside><section class="registration-main"><nav class="topbar"><a class="back-link" href="#login">${icon('left')}Back to Login</a><p>Need help? Contact the <a href="#" id="office-help">Christian Formation Office</a></p></nav><header class="register-heading"><h1 tabindex="-1" id="page-title">Create Account</h1><p>Join the Christian Formation Office Recollection System</p></header><ol class="steps" aria-label="Registration progress">${labels.map((label,i)=>`<li class="${i+1===accountState.step?'active':i+1<accountState.step?'done':''}" ${i+1===accountState.step?'aria-current="step"':''}><span class="step-number">${i+1<accountState.step?icon('check'):i+1}</span>${label}</li>`).join('')}</ol><form id="registration-form">${content}</form></section></section>`;

 const form=document.querySelector('#registration-form');
 window.CfoAccountIds.bind(document.querySelector('#studentId'));
 window.CfoRegistrationEmail.bind(document.querySelector('#email'));
 form.addEventListener('input',e=>{if(e.target.name in accountState)accountState[e.target.name]=e.target.value;if(e.target.name==='email'){const preview=document.querySelector('#login-email-preview strong');if(preview){const email=accountState.email.trim().toLowerCase();preview.textContent=email.includes('@')?email.split('@')[0]+'@cfo.office.com':'Enter a Gmail address';}}if(accountState.step===2)updateRules()});
 const role=document.querySelector('#role');if(role)role.onchange=()=>{accountState.role=role.value;registration();document.querySelector('#role').focus()};
 document.querySelector('#previous').onclick=()=>{if(accountState.step===1){location.hash='login'}else{setStep(accountState.step-1)}};
 document.querySelectorAll('[data-edit]').forEach(button=>button.onclick=()=>setStep(Number(button.dataset.edit)));
 document.querySelector('#office-help').onclick=e=>{e.preventDefault();showNotice('Christian Formation Office','For registration assistance and account verification, please visit the Christian Formation Office in person.')};
 form.onsubmit=e=>{e.preventDefault();if(window.CFO_BACKEND && accountState.step===3){cfoSubmit(form,async()=>{const result=await cfoRequest('/api/register','POST',{student_id:accountState.studentId,first_name:accountState.firstName,middle_name:accountState.middleName,last_name:accountState.lastName,email:accountState.email,role:accountState.role,password:accountState.password,password_confirmation:accountState.confirmPassword});accountState.password='';accountState.confirmPassword='';accountState.step=1;location.hash='login';setTimeout(()=>showNotice('Account created','Your generated login email is '+(result.login_email||'')+'. '+result.message),0);});return;}const error=document.querySelector('#form-error');if(accountState.step===1){for(const name of ['studentId','firstName','lastName','email']){if(!accountState[name].trim()){error.textContent='Please enter your ID, first name, last name and Gmail address.';document.getElementById(name).focus();return}}if(!window.CfoAccountIds.valid(accountState.studentId)){error.textContent=window.CfoAccountIds.message;document.getElementById('studentId').focus();return}if(!window.CfoRegistrationEmail.valid(accountState.email)){error.textContent=window.CfoRegistrationEmail.message;document.getElementById('email').focus();return}setStep(2)}else if(accountState.step===2){if(!rules.every(rule=>rule.test(accountState.password))){error.textContent='Please meet all five password requirements before continuing.';document.querySelector('#password').focus();return}if(accountState.password!==accountState.confirmPassword){error.textContent='Your passwords do not match. Please re-enter your password.';document.querySelector('#confirmPassword').focus();return}setStep(3)}else{showNotice('Registration preview complete','You have completed the registration screens. This UI is not connected to the CFO registration service, so no account has been created and no information has been submitted.');accountState.password='';accountState.confirmPassword='';accountState.step=1;location.hash='login'}};
 bindReveal();if(accountState.step===2)updateRules();
}
function setStep(step){accountState.step=step;registration();document.querySelector('#page-title').focus({preventScroll:true});window.scrollTo({top:0,behavior:'instant'})}
function updateRules(){rules.forEach((rule,i)=>{const item=document.querySelector(`#rule-${i}`);const valid=rule.test(accountState.password);item.classList.toggle('valid',valid);item.querySelector('.rule-dot').textContent=valid?'✓':'';item.querySelector('.rule-status').textContent=valid?' (met)':' (not met)'});const confirm=document.querySelector('#confirmPassword');confirm.setAttribute('aria-invalid',String(Boolean(confirm.value)&&confirm.value!==accountState.password));document.querySelector('#form-error').textContent=''}


// Session-bound office recovery. Passwords stay in page memory, never browser storage.

const resetState={step:1,email:'',temporaryPassword:'',enteredTemporary:'',newPassword:'',confirmNewPassword:'',loginEmail:'',verified:false};

function clearReset(){stopRecoveryPolling();stopRecoveryRequestCountdown();clearRecoveryPassword();Object.assign(resetState,{step:1,email:'',temporaryPassword:'',enteredTemporary:'',newPassword:'',confirmNewPassword:'',loginEmail:'',verified:false})}

function resetActions(final=false){return `<p class="error" id="reset-error" role="alert"></p><div class="form-actions"><button class="outline" type="button" id="reset-back">${icon('left')}Back</button><button class="primary" type="submit">${final?icon('check'):''}${final?'Confirm':'Continue'}${final?'':icon('right')}</button></div>`}

function resetCredential(id,label,value,symbol,highlight=false){return `<div class="reset-credential"><label for="${id}">${label}</label><div class="credential-value ${highlight?'credential-highlight':''}">${icon(symbol)}<input id="${id}" type="text" readonly value="${escapeHTML(value)}" spellcheck="false"><button type="button" class="copy-button" data-copy="${id}" aria-label="Copy ${label.toLowerCase()}">${icon('copy')}</button></div></div>`}

function resetRequirements(){return `<div class="requirements">${icon('shield')}<div><h2>Password Requirements</h2><ul>${rules.map((rule,i)=>`<li id="reset-rule-${i}"><span class="rule-dot" aria-hidden="true"></span><span class="rule-text">${escapeHTML(rule.text)}</span><span class="sr-only rule-status"> (not met)</span></li>`).join('')}</ul></div></div>`}

function forgot() {
 stopRecoveryPolling();
 stopRecoveryRequestCountdown();
 document.body.classList.add('registration-mode');
 const step=resetState.step;
 const labels=['Verify Account','Enter Temporary Password','Create New Password'];
 let content;
 if(step===1) content=banner('info','Enter Your Email Address','Submit your registered Gmail or school login email, then visit the <strong>CFO Office, Miraculous Building, near the chapel</strong>, for identity verification and admin approval.')+field('reset-email','Email Address','Enter your registered Gmail or school login email','email','mail',resetState.email)+'<p id="recovery-request-limit" class="recovery-notes" role="status"></p><p class="error" id="reset-error" role="alert"></p><button class="primary reset-send" type="submit">'+icon('mail')+'Send to Reset Your Password</button><div class="divider">OR</div><div class="reset-login"><button class="outline" type="button" id="reset-back">'+icon('left')+'Back to Login</button></div>';
 else if(step===2) content='<div class="banner" id="recovery-stage-banner"><span class="recovery-status-icon" id="recovery-stage-icon">'+icon('info')+'</span><div><strong id="recovery-stage-title">Awaiting Admin Approval</strong><p id="recovery-status" role="status">Visit the CFO Office, Miraculous Building, near the chapel, for identity verification and admin approval. This page checks automatically every 5 seconds.</p></div></div><div class="recovery-status-tools"><span id="recovery-countdown" role="status"></span><button type="button" class="outline" id="check-recovery">Check approval</button></div><div id="recovery-password-panel" hidden><div class="reset-credentials">'+resetCredential('reset-username','Your School Email / Username',resetState.loginEmail,'mail')+resetCredential('reset-generated','Your Temporary Password',resetState.temporaryPassword,'key',true)+'<p id="recovery-copy-status" class="copy-status" role="status"></p><p><strong>Note:</strong> This is a temporary password. You will create a new password on the next page.</p></div></div><div id="recovery-temporary-field">'+field('reset-temporary','Enter Temporary Password','Enter the temporary password shown above','password','lock')+'</div><p class="recovery-notes">Keep this page open on this device. After admin approval, the temporary password appears here; it is not sent by email or SMS. It has no time limit and can only be used once.</p><p id="recovery-request-limit" class="recovery-notes" role="status"></p>'+resetActions();
 else content=banner('lock','Create a New Password','Please enter your new password. Make sure it meets the security requirements below.')+field('reset-new','New Password','Enter your new password','password','lock',resetState.newPassword)+field('reset-confirm','Confirm New Password','Re-enter your new password','password','lock',resetState.confirmNewPassword)+resetRequirements()+resetActions(true);
 app.innerHTML=`<section class="registration-shell forgot-shell reset-step-${step}"><aside class="brand-panel auth-brand-panel" aria-label="Christian Formation Office Recollection"><img class="auth-seal" src="{{ asset('assets/ulslogo.png') }}" alt="Christian Formation Office logo" width="180" height="180"><p class="auth-university">Christian<br><strong>Formation Office</strong></p><p class="auth-product">Recollection</p><p class="auth-quote">In faith, in community,<br>towards a better you.</p><p class="auth-motto">A Journey of Faith<br>A Brighter Tomorrow</p></aside><section class="registration-main"><nav class="topbar">${step>1?`<a class="back-link" href="#login">${icon('left')}Back to Login</a>`:''}<p>Need help? Contact the <a href="#office-help" id="recovery-office-help">Christian Formation Office</a></p></nav><header class="register-heading">${step===1?`<div class="reset-heading-icon">${icon('lock')}</div>`:''}<h1 id="page-title" tabindex="-1">Forgot Password?</h1><p>Reset your password to regain access to your account.</p></header><ol class="steps">${labels.map((label,i)=>`<li class="${i+1<step?'done':i+1===step?'active':''}" ${i+1===step?'aria-current="step"':''}><span class="step-number">${i+1<step?icon('check'):i+1}</span>${label}</li>`).join('')}</ol><form id="recovery-form">${content}</form></section></section>`;
 document.querySelector('#recovery-office-help').onclick=event=>{event.preventDefault();showNotice('Christian Formation Office','Visit the CFO Office, Miraculous Building, near the chapel, for account verification and password recovery assistance.');};
 document.querySelector('#reset-back').onclick=()=>{if(step===1)location.hash='login';else if(step===2){clearRecoveryPassword();resetState.verified=false;resetState.loginEmail='';setResetStep(1);}else setResetStep(2);};
 if(step===2){
  document.querySelector('#check-recovery').onclick=checkRecoveryStatus;
  document.querySelector('[data-copy="reset-generated"]').onclick=copyRecoveryPassword;
  document.querySelector('[data-copy="reset-username"]').onclick=copyRecoveryUsername;
  document.querySelector('#reset-temporary').setAttribute('autocomplete','one-time-code');
  startRecoveryPolling();
 }
 document.querySelector('#recovery-form').onsubmit=event=>{event.preventDefault();if(step===2){verifyTemporaryPassword();return;}if(step===3){resetState.newPassword=document.querySelector('#reset-new').value;resetState.confirmNewPassword=document.querySelector('#reset-confirm').value;document.querySelector('#reset-error').textContent='';updateResetRules();if(!rules.every(rule=>rule.test(resetState.newPassword))){resetError('Meet all password requirements before continuing.','reset-new');return;}if(resetState.newPassword!==resetState.confirmNewPassword){resetError('Your passwords do not match.','reset-confirm');return;}}cfoSubmit(event.target,async()=>{
  if(step===1){const email=document.querySelector('#reset-email').value;resetState.email=email;try{const result=await cfoRequest('/api/forgot-password','POST',{email});updateRecoveryRequestLimit(result.recovery_limit,email);setResetStep(2);showNotice('Password recovery',result.message);}catch(error){updateRecoveryRequestLimit(error.recoveryLimit,email);throw error;}}
  else {const result=await cfoRequest('/api/reset-password','POST',{password:document.querySelector('#reset-new').value,password_confirmation:document.querySelector('#reset-confirm').value});clearReset();location.hash='login';setTimeout(()=>showNotice('Password changed',result.message),0);}
 },step===2);};bindReveal();
 if(step===1) document.querySelector('#reset-email').addEventListener('input',syncRecoveryRequestLimit);
 if(step===3){document.querySelector('#reset-new').addEventListener('input',event=>{resetState.newPassword=event.target.value;event.target.removeAttribute('aria-invalid');updateResetRules();});document.querySelector('#reset-confirm').addEventListener('input',event=>{resetState.confirmNewPassword=event.target.value;event.target.removeAttribute('aria-invalid');});updateResetRules();}
 startRecoveryRequestCountdown();
}

function setResetStep(step){resetState.step=step;forgot();document.querySelector('#page-title').focus({preventScroll:true});window.scrollTo({top:0,behavior:'instant'})}
function resetError(message,id){document.querySelector('#reset-error').textContent=message;const input=document.getElementById(id);input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby','reset-error');input.focus()}
function updateResetRules(){rules.forEach((rule,i)=>{const item=document.querySelector(`#reset-rule-${i}`);const valid=rule.test(resetState.newPassword);item.classList.toggle('valid',valid);item.querySelector('.rule-dot').textContent=valid?'✓':'';item.querySelector('.rule-status').textContent=valid?' (met)':' (not met)'})}


// Authentication-only router: Login, Create Account, and Forgot Password.
function route(){
  if(location.hash!=='#forgot-password')clearReset();
  document.querySelectorAll('dialog[open]').forEach(dialog=>dialog.close());
  if(location.hash.startsWith('#register')) registration();
  else if(location.hash==='#forgot-password') forgot();
  else login();
}
window.addEventListener('hashchange', route);
if(!location.hash) history.replaceState(null,'','#login');
route();
if(new URLSearchParams(location.search).get('password_changed')==='1'){showNotice('Password changed','You have been signed out. Sign in again with your new password.');history.replaceState(null,'',location.pathname+location.hash);}
</script>
</body>
</html>
