<?php
declare(strict_types=1);
// includes/constants.php

define('SITE_NAME',   'Trans Nzoia County Recruitment Portal');
define('SITE_SHORT',  'TNC Recruitment');
define('COUNTY_EMAIL','recruitment@transnzoia.go.ke');
define('COUNTY_PHONE','+254 (053) 20604');
define('COUNTY_BOX',  'P.O. Box 4211 – 30200, Kitale, Kenya');

const SUB_COUNTIES = [
    'Cherangany' => ['Sinyereri','Kaplamai','Motosiet','Chepsiro/Kiptoror','Makutano','Cherangany/Suwerwa'],
    'Kwanza'     => ['Kwanza','Keiyo','Kapomboi','Bidii'],
    'Saboti'     => ['Machewa','Kinyoro','Saboti','Matisi','Tuwan'],
    'Endebess'   => ['Endebess','Matumbei','Chepchoina'],
    'Kiminini'   => ['Sikhendu','Nabiswa','Sirende','Waitaluk','Hospital','Kiminini'],
];

const APP_STATUSES = [
    'Received'             => ['label'=>'Received',              'class'=>'badge-neutral'],
    'Under Review'         => ['label'=>'Under Review',          'class'=>'badge-info'],
    'Shortlisted'          => ['label'=>'Shortlisted',           'class'=>'badge-success'],
    'Not Shortlisted'      => ['label'=>'Not Shortlisted',       'class'=>'badge-danger'],
    'Interview Scheduled'  => ['label'=>'Interview Scheduled',   'class'=>'badge-gold'],
    'Hired'                => ['label'=>'Hired',                 'class'=>'badge-success'],
    'Rejected'             => ['label'=>'Rejected',              'class'=>'badge-danger'],
];

const EXPERIENCE_OPTIONS = [
    'Less than 1 year',
    '1–2 years',
    '3–4 years',
    '5–7 years',
    'Over 7 years',
];

const TNC_DEPARTMENTS = [
    'Agriculture, Livestock & Fisheries',
    'Health Services',
    'Education & Early Childhood Development',
    'Public Works, Transport & Infrastructure',
    'Water, Environment & Natural Resources',
    'Finance & Economic Planning',
    'Public Service Management (PSM)',
    'Trade, Commerce & Industry',
    'Lands, Housing & Urban Development',
    'Gender, Youth, Sports & Culture',
    'County Administration & ICT',
    'Office of the Governor',
    'County Assembly'
];

const TNC_JOB_GROUPS = [
    'CPSB 01 (Job Group T)', 'CPSB 02 (Job Group S)', 'CPSB 03 (Job Group R)',
    'CPSB 04 (Job Group Q)', 'CPSB 05 (Job Group P)', 'CPSB 06 (Job Group N)',
    'CPSB 07 (Job Group M)', 'CPSB 08 (Job Group L)', 'CPSB 09 (Job Group K)',
    'CPSB 10 (Job Group J)', 'CPSB 11 (Job Group H)', 'CPSB 12 (Job Group G)',
    'CPSB 13 (Job Group F)', 'CPSB 14 (Job Group E)', 'CPSB 15 (Job Group D)',
    'Stipend / Intern'
];

const TNC_TERMS = [
    'Permanent and Pensionable',
    'Contract (3 Years)',
    'Contract (2 Years)',
    'Contract (1 Year)',
    'Locum / Temporary',
    'Internship (12 Months)'
];

const ALLOWED_DOC_TYPES  = ['application/pdf','application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg','image/png'];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
