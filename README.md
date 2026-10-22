# BillSplitter

A small PHP and MySQL app for splitting restaurant bills with friends.

Upload a receipt, enter the items and choose who shares each one. BillSplitter calculates
what each person owes and provides a read-only link to the summary. For a quick equal split,
the guest calculator works without an account.

## Features

- Register, log in and manage your profile.
- Save JPG or PNG receipts and enter item names, prices and quantities manually.
- Assign an item to one person or share it among several participants.
- Split the entire bill evenly and see which items remain unassigned.
- Share a summary through a link or print it from the browser.
- Calculate a guest split in the browser without saving the bill.

The app uses plain PHP with MySQLi prepared statements, MySQL, Bootstrap 5 and JavaScript.
There is no framework or build step.

## How the split works

Prices are calculated in integer cents. Each item is divided equally among its assigned
participants. Any remaining cents go to the lowest participant IDs, so the assigned amounts
add up to the item's exact price. For example, $10 shared by three people becomes
$3.34, $3.33 and $3.33. Unassigned items remain outside the amount due.

Receipts are used as a visual reference; item entry is manual. The app does not process
payments or send emails or reminders. Amounts are displayed in dollars.

## Local setup

Requirements: PHP 8.1+ with MySQLi, Fileinfo and sessions, plus MySQL 8+ or MariaDB.

```bash
git clone https://github.com/nachopalomares/billsplitter.git
cd billsplitter
mysql -u root -p < database.sql
cp .env.example ../billsplitter.env
```

Edit `../billsplitter.env` with your database credentials. Keep this file outside the web root.
The SQL creates an empty `bill_splitter` database;
it does not include a database user or sample accounts. Give your application database user
access to that database.

Load the environment variables and start PHP from the repository root:

```bash
set -a
. ../billsplitter.env
set +a
php -S localhost:8000
```

Open [localhost:8000](http://localhost:8000). These shell commands work in Bash and Zsh;
on other systems, set the same environment variables using your shell's syntax.
The app reads environment variables directly, so the configuration file must be loaded by the shell.
Bootstrap, icons and fonts are loaded from CDNs.

## Receipt storage and sharing

The default receipt directory is `billsplitter-storage/receipts`, beside the repository.
Set `UPLOAD_DIR` to change it. Keep that directory outside the web root and writable by PHP.
Images are limited to 5 MB and their content is checked on upload.

A bill and its image can be viewed by its owner or someone holding its random share link.
Anyone with that link can see the receipt, participant details and amounts. Links do not
expire automatically. Editing requires the owner's login.

For an existing installation, back up the database and apply the migration once:

```bash
mysql -u root -p bill_splitter < migrations/001_share_tokens.sql
```

Move existing receipt files from `uploads/` into the private receipt directory before
updating the application. Existing bills receive a share token when their owner opens the
summary. Old links containing only a bill ID are no longer public share links.

## Project files

| Path | Purpose |
| --- | --- |
| `config/` | Environment settings, sessions and database connection |
| `includes/` | Shared layout and bill calculations |
| `upload_receipt.php` | Image upload and manual item entry |
| `split_bill.php` | Participants and item assignments |
| `view_bill.php` | Read-only summary and share link |
| `receipt.php` | Access-controlled receipt images |
| `anonymous.php` | Guest calculator |
| `database.sql` | Schema for a new installation |

The scope is a single-currency bill calculator with saved receipts, rather than a payment
service. Taxes and tips can be entered as separate items.
