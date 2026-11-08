# นัดเพื่อน

เว็บนัดเพื่อน: ผู้ใช้เลือกวันว่างในปฏิทิน หัวหน้ากลุ่มเชิญเพื่อนด้วย username ระบบหาวันที่สมาชิกทุกคนว่างตรงกัน แล้วหัวหน้ายืนยันเป็นนัดของกลุ่ม

PHP (mysqli) + MySQL ไม่ใช้ framework

## ติดตั้ง

1. Import `schema.sql` ผ่าน phpMyAdmin เข้า database ของตัวเอง
2. ก๊อป `config/db.example.php` เป็น `config/db.php` แล้วใส่ username, password, ชื่อ database
3. อัปโหลดทั้งโฟลเดอร์ขึ้นเซิร์ฟเวอร์ แล้วเปิด `register.php`

`config/db.php` อยู่ใน `.gitignore` ห้าม commit

## หน้าต่างๆ

| ไฟล์ | หน้าที่ |
|---|---|
| `register.php` / `login.php` / `logout.php` | สมัคร เข้า ออกจากระบบ |
| `index.php` | หน้าหลัก: แบนเนอร์นัดวันนี้, คำเชิญที่รอตอบ, นัดที่จะถึง |
| `calendar.php` | ปฏิทินรายเดือน กดวันเพื่อสลับว่าง/ไม่ว่าง |
| `free_days.php` | สรุปรายการวันว่างทั้งหมด |
| `groups.php` | กลุ่มของฉัน + สร้างกลุ่ม |
| `group.php?id=` | สมาชิก, เชิญเพื่อน, วันที่ทุกคนว่าง, ยืนยันนัด, รายการนัด |
| `invite_respond.php` | รับ/ปฏิเสธคำเชิญ |

## จุดสำคัญ

- ทุก query ใช้ prepared statement (`includes/auth.php`: `db_rows`, `db_row`, `db_exec`)
- รหัสผ่านเก็บด้วย `password_hash`, login แล้ว `session_regenerate_id(true)`
- ทุกฟอร์ม POST มี CSRF token, ข้อความที่แสดงผ่าน `htmlspecialchars` (`e()`)
- เชิญและยืนยันนัดได้เฉพาะหัวหน้ากลุ่ม, เปิดหน้ากลุ่มได้เฉพาะสมาชิกที่รับคำเชิญแล้ว
- สร้างกลุ่มใช้ transaction (insert กลุ่ม + insert หัวหน้าเป็นสมาชิก)
- การแจ้งเตือนเที่ยงคืนแทนด้วยแบนเนอร์ "วันนี้มีนัด" ที่หน้าหลัก

Query หาวันที่ทุกคนว่างตรงกัน (`group.php`):

```sql
SELECT a.free_date
FROM availability a
JOIN group_members gm ON gm.user_id = a.user_id AND gm.status = 'accepted'
WHERE gm.group_id = ? AND a.free_date >= ?
GROUP BY a.free_date
HAVING COUNT(*) = (SELECT COUNT(*) FROM group_members
                   WHERE group_id = ? AND status = 'accepted');
```
