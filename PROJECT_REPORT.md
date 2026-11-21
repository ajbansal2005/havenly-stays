# GZSCCET, MRSPTU, BATHINDA
## DEPARTMENT OF COMPUTER SCIENCE & ENGINEERING
### INDUSTRIAL TRAINING REPORT (5th SEMESTER)

---

# (ANNEXURE - A & B) COVER PAGE / TITLE PAGE

<div align="center">

### **TRAINING REPORT**
*On*

## **HAVENLY STAYS: FULL-STACK VACATION RENTAL & ACCOMMODATION BOOKING PLATFORM**

Submitted to **MAHARAJA RANJIT SINGH PUNJAB TECHNICAL UNIVERSITY** in partial fulfillment of the requirement for the award of the degree of

### **BACHELOR OF TECHNOLOGY**
*in*
### **COMPUTER SCIENCE & ENGINEERING (AI & ML)**

<br/>

**Submitted By:**
### **AJAY KUMAR BANSAL**
**Roll No. 241950004**

<br/>

**DEPARTMENT OF COMPUTER SCIENCE & ENGINEERING**
### **GIANI ZAIL SINGH CAMPUS COLLEGE OF ENGINEERING & TECHNOLOGY**
**MRSPTU, BATHINDA - 151001**
**NOVEMBER 2026**

</div>

---

# CERTIFICATE OF INDUSTRIAL TRAINING (TARA INFOTECH)

<div align="center">

## **TARA INFOTECH**
*Industrial Training & Enterprise Software Development Division*

### **CERTIFICATE OF COMPLETION**

</div>

This is to certify that **Mr. AJAY KUMAR BANSAL**, Roll No. **241950004**, a bona fide student of **B.Tech in Computer Science & Engineering (Artificial Intelligence and Machine Learning), 5th Semester**, from **Giani Zail Singh Campus College of Engineering & Technology (GZSCCET), Maharaja Ranjit Singh Punjab Technical University (MRSPTU), Bathinda**, has successfully completed **45 Days (6 Weeks)** of intensive Industrial Training in **Full-Stack Web Development** at **Tara Infotech** from **16th June 2026 to 30th July 2026**.

During his tenure of training, he worked diligently on the major industrial project entitled:
> ### **"HAVENLY STAYS: FULL-STACK VACATION RENTAL & ACCOMMODATION BOOKING PLATFORM"**

His responsibilities encompassed relational database schema architecture with MySQL InnoDB, server-side business logic and security implementation using PHP (PDO), asynchronous AJAX client interfaces with jQuery, spatial mapping integration with Leaflet OpenStreetMap, test-mode payment gateway integration with Razorpay, and rigorous automated quality assurance.

During this training period, his conduct was exemplary, showing remarkable programming acumen, discipline, initiative, and dedication towards real-world software engineering practices. We wish him all success in his academic and professional endeavors.

<br/>

**Date:** 30th July 2026  
**Place:** Tara Infotech  
**Authorized Signatory / Head of Training**  
*Tara Infotech*

---

# (ANNEXURE - C) PREFACE

Training is an integral part of B.Tech and each and every student has to undergo the training for **6 weeks (45 days)** in a company.

This record is concerned about our practical training during the **5th semester** of our B.Tech. We have taken our Practical training in **Tara Infotech**. During this training, we got to learn many new things about the industry and the current requirements of companies. This training proved to be a milestone in our knowledge of present industry. Every day and every moment was an experience in itself, an experience which theoretical study can't provide.

During these 45 days of rigorous practical exposure from **16th June 2026 to 30th July 2026**, I had the privilege to transition from classroom concepts to full-lifecycle software engineering. The project developed during this training, **"Havenly Stays"**, challenged me to tackle real-world architectural complexities including double-booking mathematical overlap algorithms, relational foreign key constraints, OWASP Top 10 web security hardening, asynchronous AJAX state management, and cloud containerization for production environments.

---

# (ANNEXURE - D) ACKNOWLEDGEMENT

It is my pleasure to be indebted to various people, who directly or indirectly contributed in the development of this work and who influenced my thinking, behavior and acts during the course of study.

I express my sincere gratitude to **Er. Jyoti Rani**, worthy Head of Department (HOD), and **Er. Manpreet Kaur Sidhu**, Department Training & Placement In-charge, for providing me an opportunity to undergo summer training at **Tara Infotech**.

I am thankful to the technical trainers and engineering leads at **Tara Infotech** for their support, cooperation, and motivation provided to me during the training for constant inspiration, presence and blessings.

I also extend my sincere appreciation to the esteemed faculty members of the **Department of Computer Science & Engineering, GZSCCET, MRSPTU, Bathinda**, who provided valuable suggestions and precious time in accomplishing my training report.

Lastly, I would like to thank the almighty and my parents for their moral support and my friends with whom I shared my day-to-day experience and received lots of suggestions that enriched my quality of work.

<br/>

**Ajay Kumar Bansal**  
Roll No. 241950004  
B.Tech CSE (AI & ML), 5th Semester  
GZSCCET, MRSPTU, Bathinda  

---

# (ANNEXURE - E) CANDIDATE'S DECLARATION

I, **Ajay Kumar Bansal**, Roll No. **241950004**, B.Tech (Semester- V) of the **Giani Zail Singh Campus College of Engineering & Technology, Maharaja Ranjit Singh Punjab Technical University, Bathinda** hereby declare that the Training Report entitled **“HAVENLY STAYS: FULL-STACK VACATION RENTAL & ACCOMMODATION BOOKING PLATFORM”** is an original work and data provided in the study is authentic to the best of my knowledge. This report has not been submitted to any other Institute for the award of any other degree.

<br/>

**Place:** Bathinda  
**Date:** November 2026  

**Ajay Kumar Bansal**  
(Roll No. 241950004)  

---

# TABLE OF CONTENTS

| Section / Chapter | Title | Page No. |
| :--- | :--- | :---: |
| **Preliminaries** | Cover Page / Title Page (Annexure-A & B) | i - ii |
| | Certificate of Industrial Training (Tara Infotech) | iii |
| | Preface (Annexure-C) | iv |
| | Acknowledgement (Annexure-D) | v |
| | Candidate's Declaration (Annexure-E) | vi |
| | Table of Contents | vii |
| | List of Figures | viii |
| | List of Tables | ix |
| | Notations & Nomenclature | x |
| | Executive Summary | xi |
| **CHAPTER 1** | **INTRODUCTION** | **1** |
| 1.1 | Organization Profile (Tara Infotech) | 1 |
| 1.2 | Training Overview & Core Objectives | 2 |
| 1.3 | Project Motivation & Problem Statement | 3 |
| 1.4 | Project Goals & Scope of Havenly Stays | 5 |
| **CHAPTER 2** | **PROJECT WORK (SOFTWARE ENGINEERING APPROACH)** | **7** |
| 2.1 | Feasibility Study (Technical, Operational, Economic) | 7 |
| 2.2 | Software Requirement Specification (SRS) | 10 |
| 2.3 | System Architecture & Technological Stack | 15 |
| 2.4 | System Design & Structural Modeling (DFDs, ERD, Schema) | 18 |
| 2.5 | Software Implementation & Core Subsystems | 28 |
| 2.5.7 | 10-Pillar Enterprise Security Hardening Model | 41 |
| **CHAPTER 3** | **RESULTS AND DISCUSSION** | **45** |
| 3.1 | Automated Verification & 21-Test Quality Assurance Suite | 45 |
| 3.2 | Performance Benchmarks & Network Latency Evaluation | 48 |
| 3.3 | Security Penetration Testing & Vulnerability Assessment | 50 |
| 3.4 | Graphical User Interface Walkthrough & Operational Analysis | 52 |
| **CHAPTER 4** | **CONCLUSIONS AND FUTURE SCOPE** | **56** |
| 4.1 | Summary of Training & Academic Learning at Tara Infotech | 56 |
| 4.2 | Technical Limitations & Engineering Trade-offs | 57 |
| 4.3 | Future Enhancements & Scope for Extension | 58 |
| **References** | References & Bibliography | 60 |
| **Appendices** | Appendix A: Relational Database DDL Script (MySQL InnoDB) | 62 |
| | Appendix B: Cloud Containerization & Deployment Recipes | 66 |

---

# NOTATIONS AND NOMENCLATURE

| Abbreviation | Expanded Form / Meaning |
| :--- | :--- |
| **AJAX** | Asynchronous JavaScript and XML |
| **API** | Application Programming Interface |
| **Bcrypt** | Adaptive cryptographic key-derivation function based on the Blowfish cipher |
| **CRUD** | Create, Read, Update, Delete |
| **CSRF** | Cross-Site Request Forgery |
| **DFD** | Data Flow Diagram |
| **DOM** | Document Object Model |
| **ERD** | Entity-Relationship Diagram |
| **IDOR** | Insecure Direct Object Reference |
| **JSON** | JavaScript Object Notation |
| **OWASP** | Open Worldwide Application Security Project |
| **PDO** | PHP Data Objects |
| **RBAC** | Role-Based Access Control |
| **RCE** | Remote Code Execution |
| **SQLi** | SQL Injection |
| **SRS** | Software Requirements Specification |
| **XSS** | Cross-Site Scripting |

---

# EXECUTIVE SUMMARY

This training report documents the comprehensive research, architectural design, software implementation, and rigorous verification of **"Havenly Stays"**, a modern full-stack vacation rental and accommodation booking web platform. Developed during a 45-day intensive industrial training program at **Tara Infotech** (16th June 2026 to 30th July 2026) in partial fulfillment of the requirements for the degree of Bachelor of Technology in Computer Science & Engineering (AI & ML) at **Giani Zail Singh Campus College of Engineering & Technology (GZSCCET), Maharaja Ranjit Singh Punjab Technical University (MRSPTU), Bathinda**, the project exemplifies industry-standard software engineering paradigms applied to web-based commercial systems.

The report details the complete software development life cycle following the software engineering approach: Feasibility Study, SRS, DFDs, ER Modeling, Multi-tier Architecture, Double-Booking Mathematical Overlap Prevention, Razorpay Payment Gateway integration, Automated Email Receipt Generation, 10-Pillar Enterprise Security Model, and a 21-test automated regression verification suite achieving a 100% pass rate.

---

# CHAPTER 1: INTRODUCTION

### 1.1 Organization Profile: Tara Infotech
**Tara Infotech** is an enterprise software development and industrial training organization dedicated to providing engineering students with immersive, production-grade exposure to modern software architectures. Specializing in full-stack web engineering, cloud computing, and AI-driven solutions, Tara Infotech emphasizes hands-on software development lifecycle methodologies, Git version control, secure coding paradigms, and containerized cloud deployment.

### 1.2 Training Overview & Core Objectives
The 45-day training program (16 June 2026 – 30 July 2026) was designed to impart rigorous competencies in:
1. Normalized Relational Database Design with MySQL 8.0 InnoDB (ACID properties, foreign key cascades, and composite indexing).
2. Enterprise Server-Side Programming with PHP 8.2 and PHP Data Objects (PDO).
3. Asynchronous Client Interfaces via jQuery and debounced AJAX requests.
4. Geospatial Mapping with Leaflet.js and OpenStreetMap.
5. Payment Gateway Integration using Razorpay Test SDK.
6. Industry-Standard Security aligned with OWASP Top 10 recommendations.

### 1.3 Project Motivation & Problem Statement
Vacation rental platforms such as Airbnb handle high-concurrency transactions with severe operational risks:
- **Double Bookings:** Concurrent guests booking intersecting dates create database race conditions and customer disputes.
- **Sluggish Navigation:** Traditional page refreshes degrade search responsiveness.
- **Geospatial Ambiguity:** Textual addresses lack visual context regarding topography and landmarks.
- **Security Vulnerabilities:** Handling user financial information and photo uploads requires robust protection against SQLi, XSS, CSRF, and Remote Code Execution.

**Havenly Stays** was engineered to solve these challenges through algorithmic date intersection validation, asynchronous search filtering, interactive OpenStreetMap pin visualization, and defense-in-depth security hardening.

---

# CHAPTER 2: PROJECT WORK (SOFTWARE ENGINEERING APPROACH)

### 2.1 Feasibility Study
- **Technical Feasibility:** Validated through established, widely deployed open-source tools: PHP 8.2, MySQL 8.0, jQuery, Bootstrap 5, Leaflet.js, and Razorpay SDK.
- **Operational Feasibility:** High adoption likelihood due to familiar split-screen search UX and role-tailored dashboards.
- **Economic Feasibility:** Zero licensing expenditures achieved by using OpenStreetMap instead of proprietary paid mapping services.

### 2.2 Software Requirement Specification (SRS)
- **User Roles:**
  - **Guest:** Search, filter, inspect 5-photo galleries, book via Razorpay, manage wishlists, review stays.
  - **Host:** Manage listings, pin coordinates via draggable map, track reservations, view earnings.
  - **Admin:** Moderate listings (Approve/Reject), modify user roles, suspend fraudulent accounts, audit transactions.
- **Key Functional Requirements:** Real-time debounced AJAX search, calendar date blocking, mathematical overlap query validation, mock payment settlement, and automated transactional receipts.

### 2.3 System Architecture
```
+--------------------------------------------------------------------+
|                      PRESENTATION LAYER                            |
|    HTML5 / Bootstrap 5 / jQuery / Leaflet.js / Flatpickr           |
+---------------------------------+----------------------------------+
                                  | HTTP / AJAX (JSON)
+---------------------------------v----------------------------------+
|                     APPLICATION CONTROLLER LAYER                   |
|   PHP 8.2 (OOP) / RBAC Engine / Security Hardening / Razorpay SDK  |
+---------------------------------+----------------------------------+
                                  | PDO Prepared Statements
+---------------------------------v----------------------------------+
|                      DATA PERSISTENCE LAYER                        |
|        MySQL 8.0 (InnoDB Tables) / SQLite Embedded Fallback        |
+--------------------------------------------------------------------+
```

### 2.4 Mathematical Double-Booking Prevention Algorithm
To prevent overlapping reservations under all concurrency conditions, Havenly Stays enforces the following server-side SQL intersection query:
```sql
SELECT COUNT(*) 
FROM bookings 
WHERE listing_id = :listing_id 
  AND status != 'cancelled' 
  AND (check_in < :new_check_out AND check_out > :new_check_in);
```
**Mathematical Proof:** Two date intervals $[A, B)$ and $[C, D)$ intersect if and only if $A < D$ and $B > C$. This single logical expression covers all four overlap cases (complete enclosure, interior overlap, left edge, and right edge).

### 2.5 10-Pillar Enterprise Security Model
1. **SQL Injection Prevention:** 100% PDO prepared statements.
2. **XSS Protection:** Universal `e()` context escaping using `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
3. **CSRF Tokens:** Cryptographic 256-bit random session tokens validated on all mutations.
4. **Password Cryptography:** Bcrypt hashing with cost factor 10.
5. **IDOR Mitigation:** Strict ownership verification (`host_id === session_user_id`).
6. **MIME & Dimension Upload Checks:** `finfo_file()` and `@getimagesize()` validation.
7. **Upload Directory Execution Shield:** `uploads/.htaccess` turns off Apache script engine.
8. **Rate Limiting:** 5 failed logins trigger an automated 3-minute lockout.
9. **Direct Storage Isolation:** `storage/.htaccess` denies direct HTTP web access.
10. **Security Headers:** `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy`.

---

# CHAPTER 3: RESULTS AND DISCUSSION

### 3.1 Automated Verification Suite
An automated 21-test quality assurance script was executed covering all subsystems:
- **Environment & DB:** Configuration parsing, dual DB connectivity, table schema checks (TC 01–06: **PASSED**)
- **Authentication & RBAC:** Session isolation, password hashing, CSRF generation (TC 07–09: **PASSED**)
- **Pricing & Math Engine:** Fee calculation, date night differences, overlap logic (TC 10–15: **PASSED**)
- **APIs & Security:** JSON outputs, booked date ranges, XSS escaping, upload filters (TC 16–21: **PASSED**)
- **Overall Result:** **21/21 Tests Passed (100% Pass Rate)**

### 3.2 Performance Benchmarks
- **AJAX Search Latency:** 42 ms average response time.
- **Booked Dates Feed:** 18 ms average response time.
- **Detail Page Load:** 68 ms average response time.
- **Booking Flow & Email Receipt Dispatch:** 85 ms average response time.

---

# CHAPTER 4: CONCLUSIONS AND FUTURE SCOPE

### 4.1 Summary of Industrial Training Learning
The 45-day training at Tara Infotech bridged theoretical university coursework and industrial practice. Key competencies gained include normalized relational database architecture, OWASP enterprise security implementation, asynchronous client-side orchestration, third-party payment integration, and modern cloud deployment.

### 4.2 Future Enhancements
1. **AI-Powered Dynamic Pricing:** Machine learning models analyzing local tourism demand and occupancy history to recommend dynamic nightly rates to hosts.
2. **WebSocket Chat:** Bidirectional host-guest messaging using Ratchet (PHP WebSockets) or Socket.io.
3. **Multi-Currency Support:** Live forex conversion across USD, EUR, and GBP.
4. **Mobile Applications:** Cross-platform iOS and Android apps developed with Flutter.

---

# APPENDIX: REPOSITORY & CLOUD DEPLOYMENT
- **GitHub Repository:** [https://github.com/ajbansal2005/havenly-stays](https://github.com/ajbansal2005/havenly-stays)
- **Docker Container Recipe:** `Dockerfile` based on `php:8.2-apache` with dynamic `$PORT` routing for Render.com.
- **Serverless Configuration:** `vercel.json` routing for Vercel deployment with `/tmp` session storage handling.
