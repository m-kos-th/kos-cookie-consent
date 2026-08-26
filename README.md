# KOS Cookie Consent Package

**English** | [ภาษาไทย](#kos-cookie-consent-package-ภาษาไทย)

<img width="800" height="452" alt="ScreenRecording2569-08-26at10 16 29-ezgif com-video-to-gif-converter" src="https://github.com/user-attachments/assets/9770c28b-3d97-4ac0-9506-c2e3450ab5d5" />


Cookie consent package for Concrete CMS with multilingual popup copy, per-site cookie categories, consent logs/export, and gated tracking-code injection.

For a shorter Thai summary for PM/client handoff, see [PACKAGE_DETAILS.md](PACKAGE_DETAILS.md).  
For a deeper engineering map, see [CONTEXT.md](CONTEXT.md).

## Current Scope

- Frontend cookie banner and preference modal are rendered automatically by the package.
- Cookie categories, copy, logs, and export are managed from Dashboard single pages.
- Visitors can use `Accept All`, `Reject All`, or `Save Preferences`.
- Optional cookies default to `false` until the visitor explicitly opts in.
- Tracking code is injected only for consented categories.
- Saved visitors get a `Cookie Settings` entry point to reopen the modal later.
- Cookie categories can define first-party cookies to clear when consent is withdrawn.

## Consent Model

The package now uses a first-party browser identifier, not visitor IP, as the main consent key.

- Primary identity: `kos_cookie_consent_id`
- Scope: per browser/profile, per site, per consent version
- Server-side lookup key: `consent_id + site_id + consent_version`
- Audit-only field: visitor IP is still stored in logs, but it is not used to determine consent state

What this means in practice:

- Same browser profile keeps the same consent state.
- Different browsers, incognito windows, or devices get separate consent states.
- Deleting `kos_cookie_consent_id` makes that browser look like a new visitor.
- Changing cookie definitions or popup copy changes `consent_version`, so the visitor is prompted again.

## Visitor Behavior

### First Visit

- Frontend JS creates or reuses `kos_cookie_consent_id`.
- JS calls `GET /kos-cookie-consent/state`.
- If no saved state exists for the current `consent_id + site_id + consent_version`, the banner is shown.

### Accept All

- All optional toggles are turned on.
- Consent is saved.
- Banner is hidden.
- `Cookie Settings` remains available for later changes.

### Reject All

- All optional toggles are turned off.
- Consent is still saved as an answered state.
- Banner is hidden and should not reappear until consent version changes or the browser gets a new `consent_id`.

### Save Preferences

- Only the currently selected optional toggles are saved.
- Saving with everything off is valid and counts as an answered state.
- Withdrawing previously granted categories clears the configured first-party cookies for those categories and reloads the page so the next execution pass respects the updated consent state.

### Closing The Modal

- Closing the modal is not treated as consent.
- If the visitor has not answered yet, the banner remains the first layer.
- If consent is already saved and the modal was reopened from `Cookie Settings`, closing the modal only closes the modal.

## Browser Cookies

The package uses these browser cookies:

- `kos_cookie_consent_id`: first-party random identifier used as the consent key
- `consent`: marker cookie set after a successful save
- `consent_version`: browser-side marker of the last saved consent version

Important:

- Consent-cookie retention is configurable from `/dashboard/cookie_consent/setting`.
- Default retention is `30 days`.
- Allowed retention is `1-400 days` so dashboard input stays within modern browser persistence caps.
- These cookies are written as first-party cookies with `Path=/`, `SameSite=Lax`, and `Secure` when the site is served over HTTPS.
- The source of truth is still the database log keyed by `consent_id + site_id + consent_version`.
- There is no separate server-side consent-expiry rule yet beyond consent-version changes; the configurable retention applies to browser cookies only.

## Managed Cookie Cleanup

Each optional cookie category can define first-party cookies that should be removed when a visitor withdraws consent.

- Configure this on each cookie category in `/dashboard/cookie_consent/setting`.
- Enter one cookie name per line.
- Use `*` only at the end for prefix matches, for example `_ga_*`.
- Cookie path defaults to `/`.
- Domain override is optional and should be a hostname only.

Important limitations:

- This cleanup is for first-party cookies on your own site domain.
- Browser rules do not allow this package to delete third-party cookies stored on another vendor domain.
- `HttpOnly` cookies cannot be removed from frontend JavaScript.
- Do not add `kos_cookie_consent_id`, `consent`, or `consent_version` to cleanup rules because they are required to remember the visitor choice.

## Database Model

Core tables:

- `_KosCookie`: cookie categories and tracking snippets
- `_KosCookieContent`: translated cookie names and descriptions
- `_KosIP`: legacy table name, now used for consent identity rows
- `_KosLog`: consent log rows
- `_KosCookiePopupCopy`: multilingual popup copy per site/language

Important details:

- `_KosIP.consent_id` stores the browser-level consent identity.
- `_KosIP.IP` stores visitor IP for audit/history only.
- `_KosLog` stores one row per selected cookie ID.
- If a visitor saves with no optional cookie selected, the package stores a sentinel `cid = 0` row so `Reject All` and all-off preferences still count as saved consent.

## Dashboard Pages

- `/dashboard/cookie_consent`
- `/dashboard/cookie_consent/logs`
- `/dashboard/cookie_consent/setting`
- `/dashboard/cookie_consent/popup_content`
- `/dashboard/cookie_consent/export`

### Logs / Export

Logs and CSV export currently show:

- `Consent ID`
- `IP Address`
- per-cookie YES/NO columns
- `Consent Version`
- `Created` timestamp

Timestamps are stored in UTC and displayed/exported in ICT through the existing helper methods.

## Popup Copy Fields

Popup copy is stored in `_KosCookiePopupCopy` and managed per site-language row.

Current fields:

- `banner_title`
- `banner_summary`
- `modal_title`
- `modal_intro_one`
- `modal_intro_two`
- `preferences_title`
- `save_label`
- `accept_label`
- `decline_label`
- `settings_label`

Behavior notes:

- `modal_intro_two` is optional. If left blank, the second paragraph is not rendered on the frontend.
- `settings_label` is reused for both the floating `Cookie Settings` trigger and the banner `Customize` action label.

## Frontend UI Notes

Stable BEM-style classes live under `kos-cookie-popup__*`.

Useful button classes for overrides include:

- `kos-cookie-popup__button--customize`
- `kos-cookie-popup__button--reject`
- `kos-cookie-popup__button--accept`
- `kos-cookie-popup__button--save`
- `kos-cookie-popup__button--banner-customize`
- `kos-cookie-popup__button--banner-reject`
- `kos-cookie-popup__button--banner-accept`
- `kos-cookie-popup__button--modal-reject`
- `kos-cookie-popup__button--modal-save`
- `kos-cookie-popup__button--modal-accept`

## Routes

Legacy-compatible routes:

- `POST /cookieConsentSave`
- `POST /cookieConsentSaveV2`
- `GET /cookieConsentStateV2`

Current frontend routes:

- `POST /kos-cookie-consent/save`
- `GET /kos-cookie-consent/state`

The `state` response is sent with `no-store` headers to reduce cache-related consent bugs.

## Upgrade Notes

- Current package version: `0.2.9`
- Run package upgrade after deploying package code changes.
- Clear Concrete CMS cache after upgrade.
- Existing legacy IP-based rows can be adopted into the new `consent_id` model on first fresh lookup, but new saves should always use `consent_id`.

## Developer Notes

- Keep `_Kos*` table names for backwards compatibility.
- Do not change route paths unless you update both PHP and JS consumers.
- Keep `kos-cookie-popup__*` class names stable unless you also update theme overrides.
- Optional cookies should remain default-off unless product/legal requirements explicitly change.
- Managed cookie cleanup rules should only target first-party cookies that belong to the same consent category.
- If you add popup copy fields, update:
  - table creation/migration in `controller.php`
  - dashboard form/controller
  - service read/write mapping
  - frontend rendering
  - consent version expectations

## Quick Validation Checklist

- Banner appears for a new browser/profile.
- `Reject All` hides the banner and keeps `saved: true` on later page loads.
- `Accept All` restores saved toggles on reload.
- `Save Preferences` persists mixed selections correctly.
- `Cookie Settings` reopens the modal after consent is saved.
- Changing popup copy or cookie definitions produces a new consent version and re-prompts visitors.
- Changing cookie retention updates the consent-cookie max-age used by later state/save responses.
- Withdrawing previously granted consent clears the configured first-party cookies for the revoked categories.
- Logs and export show `Consent ID`, `IP Address`, and consent version correctly.

---

# KOS Cookie Consent Package (ภาษาไทย)

[English](#kos-cookie-consent-package) | **ภาษาไทย**

แพ็กเกจ Cookie Consent สำหรับ Concrete CMS รองรับข้อความป๊อปอัปหลายภาษา กำหนดหมวดคุกกี้แยกตามไซต์ มีระบบบันทึก/ส่งออกข้อมูลการยินยอม และควบคุมการฝังโค้ดติดตาม (tracking code) ตามการยินยอมของผู้เข้าชม

ดูสรุปภาษาไทยแบบสั้นสำหรับ PM/ลูกค้าได้ที่ [PACKAGE_DETAILS.md](PACKAGE_DETAILS.md)
ดูรายละเอียดเชิงเทคนิคเพิ่มเติมได้ที่ [CONTEXT.md](CONTEXT.md)

## ขอบเขตปัจจุบัน

- แบนเนอร์คุกกี้และหน้าต่างตั้งค่าความยินยอมถูกแสดงผลโดยอัตโนมัติจากแพ็กเกจ
- หมวดคุกกี้ ข้อความ ประวัติการยินยอม และการส่งออกข้อมูล จัดการผ่านหน้า Single Page ใน Dashboard
- ผู้เข้าชมสามารถเลือก `Accept All`, `Reject All`, หรือ `Save Preferences`
- คุกกี้ที่เป็นตัวเลือก (optional) จะปิดไว้เป็นค่าเริ่มต้นจนกว่าผู้เข้าชมจะยินยอมเอง
- โค้ดติดตามจะถูกฝังเฉพาะหมวดที่ได้รับความยินยอมแล้วเท่านั้น
- ผู้เข้าชมที่เคยบันทึกการยินยอมแล้วจะเห็นปุ่ม `Cookie Settings` เพื่อเปิดหน้าต่างตั้งค่าอีกครั้ง
- แต่ละหมวดคุกกี้สามารถกำหนดคุกกี้แบบ first-party ที่จะถูกลบเมื่อผู้เข้าชมถอนความยินยอม

## โมเดลการยินยอม (Consent Model)

ปัจจุบันแพ็กเกจใช้ตัวระบุประจำเบราว์เซอร์แบบ first-party เป็นคีย์หลักของการยินยอม แทนการใช้ IP ของผู้เข้าชม

- ตัวระบุหลัก: `kos_cookie_consent_id`
- ขอบเขต: ต่อเบราว์เซอร์/โปรไฟล์ ต่อไซต์ ต่อเวอร์ชันความยินยอม
- คีย์ค้นหาฝั่งเซิร์ฟเวอร์: `consent_id + site_id + consent_version`
- ฟิลด์สำหรับตรวจสอบย้อนหลังเท่านั้น: ยังคงบันทึก IP ของผู้เข้าชมในล็อก แต่ไม่ได้ใช้ตัดสินสถานะการยินยอม

ในทางปฏิบัติหมายความว่า

- โปรไฟล์เบราว์เซอร์เดิมจะคงสถานะการยินยอมเดิมไว้
- เบราว์เซอร์ต่างกัน หน้าต่าง Incognito หรืออุปกรณ์ต่างกัน จะถือเป็นสถานะการยินยอมแยกกัน
- การลบคุกกี้ `kos_cookie_consent_id` จะทำให้เบราว์เซอร์นั้นถูกมองเป็นผู้เข้าชมใหม่
- การเปลี่ยนแปลงนิยามคุกกี้หรือข้อความป๊อปอัป จะเปลี่ยน `consent_version` ทำให้ผู้เข้าชมถูกถามใหม่อีกครั้ง

## พฤติกรรมของผู้เข้าชม

### เข้าชมครั้งแรก

- ฝั่ง Frontend JS จะสร้างหรือใช้ `kos_cookie_consent_id` ที่มีอยู่ซ้ำ
- JS จะเรียก `GET /kos-cookie-consent/state`
- หากยังไม่มีสถานะที่บันทึกไว้สำหรับ `consent_id + site_id + consent_version` ปัจจุบัน แบนเนอร์จะแสดงขึ้น

### Accept All

- ตัวเลือกคุกกี้แบบ optional ทั้งหมดจะถูกเปิด
- บันทึกการยินยอม
- ซ่อนแบนเนอร์
- ปุ่ม `Cookie Settings` ยังคงใช้งานได้สำหรับแก้ไขภายหลัง

### Reject All

- ตัวเลือกคุกกี้แบบ optional ทั้งหมดจะถูกปิด
- ยังคงบันทึกเป็นสถานะที่ตอบแล้ว
- ซ่อนแบนเนอร์ และจะไม่แสดงซ้ำจนกว่า consent version จะเปลี่ยน หรือเบราว์เซอร์ได้รับ `consent_id` ใหม่

### Save Preferences

- บันทึกเฉพาะตัวเลือก optional ที่เลือกไว้ ณ ขณะนั้น
- การบันทึกโดยปิดทุกตัวเลือกถือว่าถูกต้องและนับเป็นสถานะที่ตอบแล้ว
- การถอนความยินยอมหมวดที่เคยอนุญาตไว้ จะลบคุกกี้ first-party ที่กำหนดไว้สำหรับหมวดนั้น และโหลดหน้าใหม่เพื่อให้รอบการทำงานถัดไปสอดคล้องกับสถานะการยินยอมล่าสุด

### การปิดหน้าต่าง (Modal)

- การปิดหน้าต่างไม่ถือเป็นการยินยอม
- หากผู้เข้าชมยังไม่เคยตอบ แบนเนอร์จะยังคงเป็นชั้นแรกที่แสดง
- หากมีการบันทึกความยินยอมไว้แล้ว และหน้าต่างถูกเปิดใหม่จาก `Cookie Settings` การปิดหน้าต่างจะเป็นเพียงการปิดหน้าต่างเท่านั้น

## คุกกี้ในเบราว์เซอร์

แพ็กเกจใช้คุกกี้ในเบราว์เซอร์เหล่านี้

- `kos_cookie_consent_id`: ตัวระบุแบบสุ่ม first-party ที่ใช้เป็นคีย์การยินยอม
- `consent`: คุกกี้เครื่องหมายที่ตั้งค่าหลังบันทึกสำเร็จ
- `consent_version`: ตัวเลขบ่งชี้เวอร์ชันความยินยอมล่าสุดที่บันทึกไว้ฝั่งเบราว์เซอร์

สิ่งสำคัญ:

- ระยะเวลาการเก็บคุกกี้ความยินยอมสามารถตั้งค่าได้จาก `/dashboard/cookie_consent/setting`
- ค่าเริ่มต้นคือ `30 วัน`
- ระยะเวลาที่อนุญาตคือ `1-400 วัน` เพื่อให้ค่าที่กรอกใน Dashboard อยู่ในขีดจำกัดการคงอยู่ของเบราว์เซอร์สมัยใหม่
- คุกกี้เหล่านี้ถูกเขียนเป็น first-party cookie ด้วย `Path=/`, `SameSite=Lax` และ `Secure` เมื่อไซต์ให้บริการผ่าน HTTPS
- แหล่งข้อมูลหลักที่แท้จริงยังคงเป็นล็อกในฐานข้อมูล ซึ่งคีย์ด้วย `consent_id + site_id + consent_version`
- ยังไม่มีกฎหมดอายุความยินยอมฝั่งเซิร์ฟเวอร์แยกต่างหาก นอกเหนือจากการเปลี่ยน consent version ระยะเวลาที่ตั้งค่าได้จะใช้กับคุกกี้ในเบราว์เซอร์เท่านั้น

## การล้างคุกกี้ที่จัดการได้ (Managed Cookie Cleanup)

แต่ละหมวดคุกกี้แบบ optional สามารถกำหนดคุกกี้ first-party ที่ควรถูกลบเมื่อผู้เข้าชมถอนความยินยอม

- ตั้งค่าได้ที่แต่ละหมวดคุกกี้ใน `/dashboard/cookie_consent/setting`
- กรอกชื่อคุกกี้บรรทัดละหนึ่งชื่อ
- ใช้ `*` ได้เฉพาะท้ายชื่อ สำหรับการจับคู่แบบ prefix เช่น `_ga_*`
- Path ของคุกกี้เป็น `/` โดยค่าเริ่มต้น
- การกำหนด Domain เองเป็นทางเลือก และควรเป็นชื่อโฮสต์เท่านั้น

ข้อจำกัดสำคัญ:

- การล้างคุกกี้นี้ใช้ได้เฉพาะคุกกี้ first-party บนโดเมนของไซต์ตนเองเท่านั้น
- กฎของเบราว์เซอร์ไม่อนุญาตให้แพ็กเกจนี้ลบคุกกี้ third-party ที่เก็บอยู่บนโดเมนของผู้ให้บริการรายอื่น
- คุกกี้ที่เป็น `HttpOnly` ไม่สามารถลบได้จาก JavaScript ฝั่ง Frontend
- ห้ามเพิ่ม `kos_cookie_consent_id`, `consent`, หรือ `consent_version` ลงในกฎการล้างคุกกี้ เนื่องจากจำเป็นต่อการจดจำการเลือกของผู้เข้าชม

## โมเดลฐานข้อมูล

ตารางหลัก:

- `_KosCookie`: หมวดคุกกี้และสนิปเป็ตโค้ดติดตาม
- `_KosCookieContent`: ชื่อและคำอธิบายคุกกี้ที่แปลแล้ว
- `_KosIP`: ชื่อตารางเดิม (legacy) ปัจจุบันใช้เก็บแถวข้อมูลระบุตัวตนการยินยอม
- `_KosLog`: แถวบันทึกล็อกการยินยอม
- `_KosCookiePopupCopy`: ข้อความป๊อปอัปหลายภาษาต่อไซต์/ภาษา

รายละเอียดสำคัญ:

- `_KosIP.consent_id` เก็บตัวระบุความยินยอมระดับเบราว์เซอร์
- `_KosIP.IP` เก็บ IP ของผู้เข้าชมไว้เพื่อการตรวจสอบย้อนหลัง/ประวัติเท่านั้น
- `_KosLog` เก็บหนึ่งแถวต่อหนึ่งรหัสคุกกี้ที่ถูกเลือก
- หากผู้เข้าชมบันทึกโดยไม่เลือกคุกกี้ optional ใดเลย แพ็กเกจจะบันทึกแถว sentinel `cid = 0` เพื่อให้ `Reject All` และการปิดทุกตัวเลือกยังนับเป็นการยินยอมที่บันทึกแล้ว

## หน้า Dashboard

- `/dashboard/cookie_consent`
- `/dashboard/cookie_consent/logs`
- `/dashboard/cookie_consent/setting`
- `/dashboard/cookie_consent/popup_content`
- `/dashboard/cookie_consent/export`

### ล็อก / การส่งออกข้อมูล

ล็อกและไฟล์ CSV ที่ส่งออกในปัจจุบันแสดง:

- `Consent ID`
- `IP Address`
- คอลัมน์ YES/NO ต่อคุกกี้แต่ละตัว
- `Consent Version`
- เวลาที่สร้าง (`Created`)

เวลาถูกเก็บเป็น UTC และแสดงผล/ส่งออกเป็นเวลา ICT ผ่านเมธอด helper ที่มีอยู่

## ฟิลด์ข้อความป๊อปอัป

ข้อความป๊อปอัปถูกเก็บใน `_KosCookiePopupCopy` และจัดการต่อแถวไซต์-ภาษา

ฟิลด์ปัจจุบัน:

- `banner_title`
- `banner_summary`
- `modal_title`
- `modal_intro_one`
- `modal_intro_two`
- `preferences_title`
- `save_label`
- `accept_label`
- `decline_label`
- `settings_label`

หมายเหตุพฤติกรรม:

- `modal_intro_two` เป็นฟิลด์ที่ไม่บังคับ หากเว้นว่างไว้ ย่อหน้าที่สองจะไม่แสดงผลฝั่ง Frontend
- `settings_label` ถูกใช้ซ้ำทั้งสำหรับปุ่มลอย `Cookie Settings` และป้ายปุ่ม `Customize` บนแบนเนอร์

## หมายเหตุ UI ฝั่ง Frontend

คลาสแบบ BEM ที่มีความเสถียรอยู่ภายใต้ `kos-cookie-popup__*`

คลาสปุ่มที่มีประโยชน์สำหรับการปรับแต่ง (override) ได้แก่:

- `kos-cookie-popup__button--customize`
- `kos-cookie-popup__button--reject`
- `kos-cookie-popup__button--accept`
- `kos-cookie-popup__button--save`
- `kos-cookie-popup__button--banner-customize`
- `kos-cookie-popup__button--banner-reject`
- `kos-cookie-popup__button--banner-accept`
- `kos-cookie-popup__button--modal-reject`
- `kos-cookie-popup__button--modal-save`
- `kos-cookie-popup__button--modal-accept`

## Routes

Routes ที่รองรับแบบ legacy:

- `POST /cookieConsentSave`
- `POST /cookieConsentSaveV2`
- `GET /cookieConsentStateV2`

Routes ฝั่ง Frontend ปัจจุบัน:

- `POST /kos-cookie-consent/save`
- `GET /kos-cookie-consent/state`

การตอบกลับของ `state` จะถูกส่งพร้อม header `no-store` เพื่อลดปัญหาเกี่ยวกับแคชที่เกี่ยวข้องกับการยินยอม

## หมายเหตุการอัปเกรด

- เวอร์ชันแพ็กเกจปัจจุบัน: `0.2.9`
- ให้รันการอัปเกรดแพ็กเกจหลังจากดีพลอยโค้ดแพ็กเกจใหม่ทุกครั้ง
- ล้างแคชของ Concrete CMS หลังการอัปเกรด
- แถวข้อมูลแบบ legacy ที่อิง IP เดิม สามารถถูกปรับให้เข้ากับโมเดล `consent_id` ใหม่ได้เมื่อมีการค้นหาครั้งแรกหลังอัปเดต แต่การบันทึกใหม่ควรใช้ `consent_id` เสมอ

## หมายเหตุสำหรับนักพัฒนา

- คงชื่อตาราง `_Kos*` ไว้เพื่อความเข้ากันได้แบบย้อนหลัง
- ห้ามเปลี่ยนเส้นทาง (route path) เว้นแต่จะอัปเดตทั้งฝั่ง PHP และ JS ที่เรียกใช้
- คงชื่อคลาส `kos-cookie-popup__*` ไว้ให้คงที่ เว้นแต่จะอัปเดตธีมที่ override ไว้ด้วย
- คุกกี้แบบ optional ควรปิดเป็นค่าเริ่มต้นเสมอ เว้นแต่ข้อกำหนดด้านผลิตภัณฑ์/กฎหมายจะระบุให้เปลี่ยนแปลงอย่างชัดเจน
- กฎการล้างคุกกี้ที่จัดการได้ ควรกำหนดเป้าหมายเฉพาะคุกกี้ first-party ที่อยู่ในหมวดความยินยอมเดียวกันเท่านั้น
- หากเพิ่มฟิลด์ข้อความป๊อปอัป ให้อัปเดต:
  - การสร้างตาราง/migration ใน `controller.php`
  - ฟอร์ม/คอนโทรลเลอร์ของ Dashboard
  - การแมปข้อมูลอ่าน/เขียนของ service
  - การแสดงผลฝั่ง Frontend
  - ข้อกำหนดของ consent version

## รายการตรวจสอบความถูกต้องอย่างย่อ

- แบนเนอร์แสดงสำหรับเบราว์เซอร์/โปรไฟล์ใหม่
- `Reject All` ซ่อนแบนเนอร์ และคงค่า `saved: true` ในการโหลดหน้าครั้งถัดไป
- `Accept All` คืนค่าตัวเลือกที่บันทึกไว้เมื่อโหลดหน้าใหม่
- `Save Preferences` บันทึกตัวเลือกแบบผสมได้ถูกต้อง
- `Cookie Settings` เปิดหน้าต่างตั้งค่าอีกครั้งได้หลังบันทึกความยินยอมแล้ว
- การเปลี่ยนข้อความป๊อปอัปหรือนิยามคุกกี้ ทำให้เกิด consent version ใหม่และถามผู้เข้าชมใหม่
- การเปลี่ยนระยะเวลาเก็บคุกกี้ อัปเดตค่า max-age ของคุกกี้ความยินยอมที่ใช้ในการตอบกลับ state/save ครั้งถัดไป
- การถอนความยินยอมที่เคยอนุญาตไว้ ลบคุกกี้ first-party ที่กำหนดไว้สำหรับหมวดที่ถูกถอนได้ถูกต้อง
- ล็อกและไฟล์ที่ส่งออกแสดง `Consent ID`, `IP Address`, และ consent version ได้ถูกต้อง
