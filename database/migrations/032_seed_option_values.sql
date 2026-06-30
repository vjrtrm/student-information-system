-- =============================================================================
-- 032_seed_option_values.sql
-- Master data seed for all 16 option lists.
-- Safe to re-run: INSERT IGNORE skips existing rows.
-- Prerequisite: run 001–031 migrations first (tables must exist).
-- =============================================================================

-- Ensure option lists are present (safe to re-run). This makes the file
-- importable standalone in tools like phpMyAdmin without running earlier
-- migration files. If you prefer strict ordering, you can skip this and
-- run migrations 001–013 first.
-- Create minimal table definitions if they do not exist so this file
-- can be imported standalone in tools like phpMyAdmin. These definitions
-- mirror the migration schema for these two tables.
CREATE TABLE IF NOT EXISTS option_lists (
	id         INT AUTO_INCREMENT PRIMARY KEY,
	list_key   VARCHAR(60) NOT NULL,
	label      VARCHAR(120) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_option_lists_key (list_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS option_values (
	id           INT AUTO_INCREMENT PRIMARY KEY,
	list_id      INT NOT NULL,
	value        VARCHAR(150) NOT NULL,
	display      VARCHAR(150) NOT NULL,
	sort_order   INT NOT NULL DEFAULT 0,
	status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
	created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_option_values_list_value (list_id, value),
	KEY idx_option_values_list (list_id),
	KEY idx_option_values_sort (list_id, sort_order),
	CONSTRAINT fk_option_values_list FOREIGN KEY (list_id) REFERENCES option_lists(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO option_lists (list_key, label) VALUES
	('community',        'Community'),
	('religion',         'Religion'),
	('blood_group',      'Blood Group'),
	('sslc_board',       'SSLC Board'),
	('hsc_board',        'HSC Board'),
	('hsc_group',        'HSC Group'),
	('medium',           'Medium of Study'),
	('education',        'Education Qualification'),
	('occupation',       'Occupation'),
	('discover_source',  'How did you discover us'),
	('choose_reason',    'Reasons for choosing us'),
	('institution_name', 'Institution Name'),
	('language',         'Language'),
	('academic_year',    'Academic Year'),
	('class',            'Class'),
	('section',          'Section');


-- 1. COMMUNITY
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'community');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'oc',  'OC (Open/General)',               1, 'active'),
(@lid, 'bc',  'BC (Backward Class)',              2, 'active'),
(@lid, 'bcm', 'BCM (BC Muslim)',                  3, 'active'),
(@lid, 'mbc', 'MBC (Most Backward Class)',        4, 'active'),
(@lid, 'dnc', 'DNC (Denotified Community)',       5, 'active'),
(@lid, 'sc',  'SC (Scheduled Caste)',             6, 'active'),
(@lid, 'sca', 'SCA (Scheduled Caste Arunthathiyar)', 7, 'active'),
(@lid, 'st',  'ST (Scheduled Tribe)',             8, 'active');

-- 2. RELIGION
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'religion');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'hindu',     'Hindu',      1, 'active'),
(@lid, 'christian', 'Christian',  2, 'active'),
(@lid, 'muslim',    'Muslim',     3, 'active'),
(@lid, 'sikh',      'Sikh',       4, 'active'),
(@lid, 'buddhist',  'Buddhist',   5, 'active'),
(@lid, 'jain',      'Jain',       6, 'active'),
(@lid, 'others',    'Others',     7, 'active');

-- 3. BLOOD GROUP
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'blood_group');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'a_pos',  'A+',   1, 'active'),
(@lid, 'a_neg',  'A-',   2, 'active'),
(@lid, 'b_pos',  'B+',   3, 'active'),
(@lid, 'b_neg',  'B-',   4, 'active'),
(@lid, 'ab_pos', 'AB+',  5, 'active'),
(@lid, 'ab_neg', 'AB-',  6, 'active'),
(@lid, 'o_pos',  'O+',   7, 'active'),
(@lid, 'o_neg',  'O-',   8, 'active');

-- 4. SSLC BOARD
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'sslc_board');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'tn_state',      'Tamil Nadu State Board',  1, 'active'),
(@lid, 'cbse',          'CBSE',                    2, 'active'),
(@lid, 'icse',          'ICSE',                    3, 'active'),
(@lid, 'matriculation', 'Matriculation',            4, 'active'),
(@lid, 'anglo_indian',  'Anglo-Indian Board',       5, 'active'),
(@lid, 'others',        'Others',                   6, 'active');

-- 5. HSC BOARD
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'hsc_board');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'tn_state', 'Tamil Nadu State Board',         1, 'active'),
(@lid, 'cbse',     'CBSE',                           2, 'active'),
(@lid, 'icse',     'ICSE / ISC',                     3, 'active'),
(@lid, 'ib',       'IB (International Baccalaureate)', 4, 'active'),
(@lid, 'nios',     'NIOS',                           5, 'active'),
(@lid, 'others',   'Others',                         6, 'active');

-- 6. HSC GROUP
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'hsc_group');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'bio_maths',        'Bio-Maths (PCB + Maths)',       1, 'active'),
(@lid, 'bio_without_maths','Biology (without Maths)',        2, 'active'),
(@lid, 'cs_maths',         'Computer Science (with Maths)', 3, 'active'),
(@lid, 'commerce',         'Commerce',                      4, 'active'),
(@lid, 'arts',             'Arts',                          5, 'active'),
(@lid, 'vocational',       'Vocational',                    6, 'active'),
(@lid, 'diploma',          'Diploma (Lateral Entry)',        7, 'active'),
(@lid, 'others',           'Others',                        8, 'active');

-- 7. MEDIUM OF STUDY
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'medium');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'tamil',     'Tamil',      1, 'active'),
(@lid, 'english',   'English',    2, 'active'),
(@lid, 'urdu',      'Urdu',       3, 'active'),
(@lid, 'telugu',    'Telugu',     4, 'active'),
(@lid, 'kannada',   'Kannada',    5, 'active'),
(@lid, 'malayalam', 'Malayalam',  6, 'active'),
(@lid, 'others',    'Others',     7, 'active');

-- 8. EDUCATION QUALIFICATION
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'education');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'illiterate',   'Illiterate',                       1,  'active'),
(@lid, 'primary',      'Primary (up to 5th)',              2,  'active'),
(@lid, 'middle',       'Middle School (up to 8th)',        3,  'active'),
(@lid, 'sslc',         'SSLC / 10th',                      4,  'active'),
(@lid, 'hsc',          'HSC / 12th',                       5,  'active'),
(@lid, 'diploma',      'Diploma',                          6,  'active'),
(@lid, 'ug',           'Under Graduate (UG)',              7,  'active'),
(@lid, 'pg',           'Post Graduate (PG)',               8,  'active'),
(@lid, 'professional', 'Professional Degree',              9,  'active'),
(@lid, 'phd',          'PhD / Doctorate',                  10, 'active'),
(@lid, 'others',       'Others',                           11, 'active');

-- 9. OCCUPATION
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'occupation');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'government',   'Government Employee',               1,  'active'),
(@lid, 'private',      'Private Employee',                  2,  'active'),
(@lid, 'business',     'Business / Self-Employed',          3,  'active'),
(@lid, 'agriculture',  'Agriculture / Farming',             4,  'active'),
(@lid, 'daily_wages',  'Daily Wages',                       5,  'active'),
(@lid, 'professional', 'Professional (Doctor / Lawyer / CA)', 6, 'active'),
(@lid, 'retired',      'Retired',                           7,  'active'),
(@lid, 'homemaker',    'Homemaker',                         8,  'active'),
(@lid, 'deceased',     'Deceased',                          9,  'active'),
(@lid, 'not_employed', 'Not Employed',                      10, 'active'),
(@lid, 'others',       'Others',                            11, 'active');

-- 10. HOW DID YOU DISCOVER US
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'discover_source');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'social_media',   'Social Media',          1,  'active'),
(@lid, 'newspaper',      'Newspaper',             2,  'active'),
(@lid, 'friend',         'Friend / Family',       3,  'active'),
(@lid, 'alumni',         'Alumni',                4,  'active'),
(@lid, 'school',         'School / Teacher',      5,  'active'),
(@lid, 'website',        'College Website',       6,  'active'),
(@lid, 'banner',         'Banner / Hoarding',     7,  'active'),
(@lid, 'tv_radio',       'TV / Radio',            8,  'active'),
(@lid, 'education_fair', 'Education Fair',        9,  'active'),
(@lid, 'others',         'Others',                10, 'active');

-- 11. REASONS FOR CHOOSING US
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'choose_reason');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'reputation',     'College Reputation',          1,  'active'),
(@lid, 'location',       'Proximity / Location',        2,  'active'),
(@lid, 'course',         'Course Availability',         3,  'active'),
(@lid, 'faculty',        'Faculty Quality',             4,  'active'),
(@lid, 'fees',           'Affordable Fees',             5,  'active'),
(@lid, 'placement',      'Placement Record',            6,  'active'),
(@lid, 'infrastructure', 'Infrastructure & Facilities', 7,  'active'),
(@lid, 'scholarship',    'Scholarship / Financial Aid', 8,  'active'),
(@lid, 'parent_choice',  'Parent / Guardian Choice',    9,  'active'),
(@lid, 'others',         'Others',                      10, 'active');

-- 12. INSTITUTION NAME (previous institutions)
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'institution_name');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'govt_school',        'Government School',              1, 'active'),
(@lid, 'govt_aided_school',  'Government Aided School',        2, 'active'),
(@lid, 'private_school',     'Private / Matriculation School', 3, 'active'),
(@lid, 'cbse_school',        'CBSE School',                    4, 'active'),
(@lid, 'icse_school',        'ICSE School',                    5, 'active'),
(@lid, 'others',             'Others',                         6, 'active');

-- 13. LANGUAGE (mother tongue)
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'language');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'tamil',     'Tamil',      1,  'active'),
(@lid, 'english',   'English',    2,  'active'),
(@lid, 'telugu',    'Telugu',     3,  'active'),
(@lid, 'kannada',   'Kannada',    4,  'active'),
(@lid, 'malayalam', 'Malayalam',  5,  'active'),
(@lid, 'hindi',     'Hindi',      6,  'active'),
(@lid, 'urdu',      'Urdu',       7,  'active'),
(@lid, 'sanskrit',  'Sanskrit',   8,  'active'),
(@lid, 'french',    'French',     9,  'active'),
(@lid, 'others',    'Others',     10, 'active');

-- 14. ACADEMIC YEAR (extend as needed)
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'academic_year');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, '2022-23', '2022-23', 1, 'active'),
(@lid, '2023-24', '2023-24', 2, 'active'),
(@lid, '2024-25', '2024-25', 3, 'active'),
(@lid, '2025-26', '2025-26', 4, 'active'),
(@lid, '2026-27', '2026-27', 5, 'active');

-- 15. CLASS / YEAR OF STUDY
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'class');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'i',   'I Year',   1, 'active'),
(@lid, 'ii',  'II Year',  2, 'active'),
(@lid, 'iii', 'III Year', 3, 'active'),
(@lid, 'iv',  'IV Year',  4, 'active'),
(@lid, 'v',   'V Year',   5, 'active');

-- 16. SECTION
SET @lid = (SELECT id FROM option_lists WHERE list_key = 'section');
INSERT IGNORE INTO option_values (list_id, value, display, sort_order, status) VALUES
(@lid, 'a', 'Section A', 1, 'active'),
(@lid, 'b', 'Section B', 2, 'active'),
(@lid, 'c', 'Section C', 3, 'active'),
(@lid, 'd', 'Section D', 4, 'active'),
(@lid, 'e', 'Section E', 5, 'active');
