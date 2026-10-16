-- ============================================================
-- BUBAPC - SAMPLE (DEMO) CONTENT
--
-- This file is OPTIONAL. It is not part of the website and
-- nothing runs it for you. It exists so the pages can be seen
-- working before the church has typed anything in: a visitor
-- opening the site then sees real cards, real pagination and
-- a real search result instead of a page of "nothing here
-- yet" boxes.
--
-- HOW TO LOAD IT
--
--   sudo mysql < database/sample-data.sql
--
-- HOW TO REMOVE IT AGAIN (leaves your own work alone)
--
--   sudo mysql -e "DELETE FROM bubapc.gallery;
--                  DELETE FROM bubapc.events; ..."
--
-- The photographs it points at are the sample-N.jpg files in
-- assets/uploads/. Delete them with:
--
--   rm assets/uploads/*/sample-*.jpg
--
-- The administrator it creates has the password
-- Bubapc2026 - change it the first time you sign in, or leave
-- it out of the file and use install.php to make your own.
-- ============================================================

USE `bubapc`;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `gallery`;
DELETE FROM `announcements`;
DELETE FROM `sermons`;
DELETE FROM `events`;
DELETE FROM `devotionals`;
DELETE FROM `departments`;
DELETE FROM `families`;
DELETE FROM `people`;
DELETE FROM `programs`;

-- ------------------------------------------------------------
-- A website administrator, so the admin panel can be opened
-- straight away. password_hash() of "Bubapc2026".
--
-- It is an ordinary row in people with a sign-in: category 'staff'
-- and status 0 keep it off the website, and username is the email
-- it signs in with.
-- ------------------------------------------------------------
INSERT INTO `people`
  (`name`, `slug`, `category`, `email`, `username`, `password`, `role`, `active`, `status`) VALUES
  ('Website Administrator', 'website-administrator', 'staff', 'admin@bubapc.local', 'admin@bubapc.local',
   '$2y$12$wOzbRtpM3BLFbqniOlPZG.HzNMq.DrKaVR7JrERl0cAu/DL8qMmPa', 'admin', 1, 0);

-- ------------------------------------------------------------
-- The weekly program: day_of_week is 0 Sunday .. 6 Saturday.
-- ------------------------------------------------------------
INSERT INTO `programs` (`title`, `day_of_week`, `start_time`, `end_time`, `sort_order`, `active`) VALUES
  ('Main Service',        0, '09:00:00', '12:00:00', 1, 1),
  ('Students Fellowship', 0, '15:30:00', '17:00:00', 2, 1),
  ('Bible Study',         2, '17:30:00', '19:00:00', 1, 1),
  ('Midweek Prayer',      3, '17:30:00', '19:00:00', 1, 1),
  ('Hour of Power',       5, '18:00:00', '19:30:00', 1, 1);

-- ------------------------------------------------------------
-- Families. They depend on nobody, so they are inserted first: the
-- students below point at a family by name through a subquery.
-- ------------------------------------------------------------
INSERT INTO `families` (`name`, `slug`, `typed`, `description`, `active`, `display_order`) VALUES
  ('The Grace Family',        'the-grace-family',        'Sis. Deborah Kintu',   'Second year and third year students. We meet on Tuesday evening and always have room for one more.', 1, 1),
  ('The Sunrise Family',      'the-sunrise-family',      'Bro. Isaac Byaruhanga','For the newest students, especially those in their first year. Nobody has to know anybody here.', 1, 2),
  ('The Family of Christ',    'the-family-of-christ',    'Pastor Ruth Nakato',   'A mixed family of students and graduates who meet on Wednesday after the prayer hour.', 1, 3),
  ('The Champions Family',    'the-champions-family',    'Elder Peter Mugisha',  'For those who are getting ready for the world after university: work, money, family and pressure.', 1, 4),
  ('The Living Water Family', 'the-living-water-family', 'Sis. Deborah Kintu',   'A quiet, gentle family for students who find big services difficult. We meet in a hall, not an auditorium.', 1, 5),
  ('The Kadeth Family',       'the-kadeth-family',       'Bro. Daniel Waire',    'Kadeth means to serve. This family serves the church on the weekends, and learns while it serves.', 1, 6);

-- ------------------------------------------------------------
-- Departments. The leader is left at 0 here and pointed at its
-- person further down, once the people exist: a department names a
-- person and a person names a department, so one of the two has to
-- wait.
-- ------------------------------------------------------------
INSERT INTO `departments` (`name`, `slug`, `leader`, `description`, `active`, `display_order`) VALUES
  ('Worship & Creative Arts', 'worship-creative-arts', 0,
   'The team that leads the singing, the instruments and everything on the platform during a service. If you sing, play or make things, there is a place for you here.', 1, 1),
  ('Media & Communications',  'media-communications', 0,
   'Photography, video, the website and the social accounts. We tell the story of what God is doing, and we tell it well.', 1, 2),
  ('Prayer & Intercession',   'prayer-intercession', 0,
   'The engine room of the church. We pray for the church, for the nation, for our families and for one another.', 1, 3),
  ('Students & Campus',      'students-campus', 0,
   'Reaching students in the lecture halls, the halls of residence and the hostel, so that nobody on this campus is left without a friend in Christ.', 1, 4),
  ('Children & Youth',       'children-youth', 0,
   'For the children of the church and for the younger students who need somewhere to belong on Sunday.', 1, 5),
  ('Ushering & Protocol',    'ushering-protocol', 0,
   'A warm, orderly welcome for everybody who walks through our doors, every single week.', 1, 6),
  ('Counselling & Care',     'counselling-care', 0,
   'A safe place to bring what you are carrying. We listen, and we stay with you through it.', 1, 7);

-- ------------------------------------------------------------
-- People. The CATEGORY is the one column that matters: it decides
-- which page each person appears on.
--
--   pastor, church_admin, elder, department_leader, family_leader
--       the leadership page (leadership.php)
--   student
--       the students list (people.php?group=students)
--   alumnus
--       the alumni list (people.php?group=alumni), and the only
--       group whose testimony is shown
--
-- "position" is just the job title, shown as typed. The families
-- and departments below are pointed at by name through a subquery
-- rather than by typing the name again, so a rename here is picked
-- up on the department page straight away.
-- ------------------------------------------------------------
INSERT INTO `people`
  (`name`, `slug`, `category`, `position`, `family`, `department`, `joined_year`,
   `graduated_year`, `bio`, `testimony`, `image`, `display_order`, `status`) VALUES
  -- ============ THE LEADERSHIP ============
  ('Bishop Samuel Okello', 'bishop-samuel-okello', 'pastor', 'Bishop', 0, 0, NULL, NULL,
   'Called to shepherd the church, and to teach the next generation of leaders.', NULL,
   'people/sample-1.jpg', 1, 1),
  ('Pastor Ruth Nakato', 'pastor-ruth-nakato', 'pastor', 'Pastor', 0, 0, NULL, NULL,
   'Oversees the word ministry and the pastoral care of the students.', NULL,
   'people/sample-2.jpg', 2, 1),
  ('Bro. Daniel Waire', 'bro-daniel-waire', 'church_admin', 'Church Admin', 0, 0, NULL, NULL,
   'Keeps the day to day running of the church and its programmes.', NULL,
   'people/sample-3.jpg', 3, 1),
  ('Sis. Grace Auma', 'sis-grace-auma', 'church_admin', 'Church Admin', 0, 0, NULL, NULL,
   'In charge of welcome, hospitality and the welcome desk team.', NULL,
   'people/sample-1.jpg', 4, 1),
  ('Pastor Moses Kirumira', 'pastor-moses-kirumira', 'elder', 'Elder', 0, 0, NULL, NULL,
   'Thirty years in the ministry, and a patient teacher of the young.', NULL,
   'people/sample-2.jpg', 5, 1),
  ('Elder Peter Mugisha', 'elder-peter-mugisha', 'elder', 'Elder', 0, 0, NULL, NULL,
   'Leads the prayer and intercession team of the church.', NULL,
   'people/sample-3.jpg', 6, 1),
  ('Sis. Ruth Namutebi', 'sis-ruth-namutebi', 'department_leader', 'Department Leader', 0,
   (SELECT id FROM `departments` WHERE `slug` = 'media-communications'), NULL, NULL,
   'Leads the media and communications department.', NULL,
   'people/sample-1.jpg', 7, 1),
  ('Bro. Isaac Byaruhanga', 'bro-isaac-byaruhanga', 'department_leader', 'Department Leader', 0,
   (SELECT id FROM `departments` WHERE `slug` = 'worship-creative-arts'), NULL, NULL,
   'Leads the worship and creative arts department.', NULL,
   'people/sample-2.jpg', 8, 1),
  ('Sis. Deborah Kintu', 'sis-deborah-kintu', 'family_leader', 'Family Leader', 0, 0, NULL, NULL,
   'Leads the Grace family of students.', NULL,
   'people/sample-3.jpg', 9, 1),

  -- ============ THE STUDENTS ============
  ('Rachel Nakimera', 'rachel-nakimera', 'student', 'House Coordinator', 
   (SELECT id FROM `families` WHERE `slug` = 'the-grace-family'),
   (SELECT id FROM `departments` WHERE `slug` = 'children-youth'), 2023, NULL,
   'Second year, and looks after the first years so they find their seats on Sunday.', NULL,
   'people/sample-3.jpg', 1, 1),
  ('Timothy Ouma', 'timothy-ouma', 'student', '',
   (SELECT id FROM `families` WHERE `slug` = 'the-grace-family'),
   (SELECT id FROM `departments` WHERE `slug` = 'worship-creative-arts'), 2023, NULL,
   'Plays the drums, and runs the sound desk on a Sunday.', NULL,
   'people/sample-1.jpg', 2, 1),
  ('Deborah Atuhaire', 'deborah-atuhaire', 'student', '',
   (SELECT id FROM `families` WHERE `slug` = 'the-sunrise-family'), 0, 2024, NULL,
   'First year. Was the last person to speak to anybody in her first week, and now hosts the
   new students&rsquo; breakfast.', NULL, 'people/sample-2.jpg', 3, 1),
  ('Samuel Kalyango', 'samuel-kalyango', 'student', '',
   (SELECT id FROM `families` WHERE `slug` = 'the-kadeth-family'),
   (SELECT id FROM `departments` WHERE `slug` = 'students-campus'), 2022, NULL,
   'Runs the students&rsquo; welcome desk in the lecture halls.', NULL,
   'people/sample-1.jpg', 4, 1),
  ('Faith Nabirye', 'faith-nabirye', 'student', '',
   (SELECT id FROM `families` WHERE `slug` = 'the-living-water-family'),
   (SELECT id FROM `departments` WHERE `slug` = 'prayer-intercession'), 2023, NULL,
   'Third year, and in the intercession team.', NULL,
   'people/sample-2.jpg', 5, 1),
  ('Joshua Lwanga', 'joshua-lwanga', 'student', '',
   (SELECT id FROM `families` WHERE `slug` = 'the-champions-family'),
   (SELECT id FROM `departments` WHERE `slug` = 'counselling-care'), 2022, NULL,
   'Final year, and helping the counselling team.', NULL,
   'people/sample-3.jpg', 6, 1),

  -- ============ THE ALUMNI ============
  ('Miriam Achieng', 'miriam-achieng', 'alumnus', '', 0, 0, 2018, 2022,
   'Graduated in 2022 and is now a secondary school teacher two towns away. Teaches a Sunday
   class for the children of the church there.',
   'I came to BUBAPC to find a quiet place to study, and stayed because somebody noticed I was
   not eating. The family I was put in looked after me before I understood that was what church
   was for. I still call them on Sunday.', 'people/sample-1.jpg', 1, 1),
  ('Andrew Byoona', 'andrew-byoona', 'alumnus', '', 0, 0, 2017, 2021,
   'Graduated in 2021. Now works in Kampala and comes back twice a year to help with the campus
   outreach week.', NULL, 'people/sample-2.jpg', 2, 1),
  ('Sarah Wanjiku', 'sarah-wanjiku', 'alumnus', '', 0, 0, 2016, 2020,
   'Graduated in 2020. Studied further, and now leads the media team of another church in the
   city while finishing her course.', NULL, 'people/sample-3.jpg', 3, 1);

-- ------------------------------------------------------------
-- Point each department at its leader now that both exist.
-- ------------------------------------------------------------
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Bro. Isaac Byaruhanga') WHERE `slug` = 'worship-creative-arts';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Sis. Ruth Namutebi')    WHERE `slug` = 'media-communications';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Elder Peter Mugisha')   WHERE `slug` = 'prayer-intercession';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Bro. Daniel Waire')     WHERE `slug` = 'students-campus';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Sis. Grace Auma')       WHERE `slug` = 'children-youth';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Bro. Daniel Waire')     WHERE `slug` = 'ushering-protocol';
UPDATE `departments` SET `leader` = (SELECT id FROM `people` WHERE `name` = 'Pastor Ruth Nakato')    WHERE `slug` = 'counselling-care';

-- ------------------------------------------------------------
-- Events. Two are in the future so the homepage countdown and
-- the "upcoming" tab have something real to show, and two are
-- in the past so the "past events" tab is not empty either.
-- ------------------------------------------------------------
INSERT INTO `events` (`title`, `slug`, `theme`, `description`, `venue`, `start_date`, `start_time`, `end_date`, `end_time`, `image`, `status`) VALUES
  ('Campus-wide Convention: A Voice to Generations', 'campus-wide-convention', 'Convention',
   'Two days with teaching, worship and a great deal of prayer, for every student on campus.\n\nThe theme of the year is our motto, and every session is built around it: what it means to be heard, what it means to carry someone else, and what it costs to speak up for somebody who cannot speak for themselves.\n\nCome for one day or for both. Lunch is provided, and there is no charge and no registration.',
   'Busitema University Main Grounds', DATE_ADD(CURDATE(), INTERVAL 21 DAY), '08:30:00', DATE_ADD(CURDATE(), INTERVAL 22 DAY), '16:00:00', 'events/sample-1.jpg', 'published'),
  ('Freshers Prayer Breakfast', 'freshers-prayer-breakfast', 'Prayer',
   'The students beginning the year eat together and pray together. A short, warm morning for anybody who has just arrived at the university and does not yet know anybody here.',
   'Faculty Hall', DATE_ADD(CURDATE(), INTERVAL 9 DAY), '07:30:00', NULL, NULL, 'events/sample-2.jpg', 'published'),
  ('Second Year Retreat at the Lake', 'second-year-retreat', 'Retreat',
   'Two nights away from the campus with the whole second year cohort, for rest, teaching and swimming.\n\nBring a sleeping mat, a Bible and a friend. Everything else, including food, is provided.',
   'Bujagali Falls', DATE_ADD(CURDATE(), INTERVAL 40 DAY), '16:00:00', DATE_ADD(CURDATE(), INTERVAL 42 DAY), '11:00:00', 'events/sample-3.jpg', 'published'),
  ('Exams Prayer Week', 'exams-prayer-week', 'Prayer',
   'The week before the examinations, the church prays together for every student sitting them. Breakfast at six each morning.',
   'Church Hall', DATE_SUB(CURDATE(), INTERVAL 30 DAY), '06:00:00', NULL, NULL, 'events/sample-3.jpg', 'published'),
  ('Visiting Day and Harvest Thanksgiving', 'visiting-day-harvest', 'Celebration',
   'Our day for alumni, families and friends, and our day of thanksgiving for the harvest of students God has given us this year.',
   'Busitema University Main Grounds', DATE_SUB(CURDATE(), INTERVAL 70 DAY), '10:00:00', NULL, '14:00:00', 'events/sample-2.jpg', 'published');

-- ------------------------------------------------------------
-- Devotionals. Thirteen of them, so the archive has more than
-- one page and the page numbers can be tried out.
-- ------------------------------------------------------------
INSERT INTO `devotionals` (`title`, `slug`, `message`, `scripture_reference`, `scripture_text`, `further_study`, `devotional_date`, `status`) VALUES
  ('The Loudest Silence', 'the-loudest-silence',
   'There is a kind of quiet that is not the absence of noise. It is the presence of something that has nothing to prove.\n\nOn the busiest day of the year, a student came to my office and said almost nothing. She sat down, and after a while she said, "I did not come to ask you anything. I just needed a place where nothing was expected of me." Then she left. She had been in the room for twenty minutes and said perhaps thirty words, and I have thought about it ever since.\n\nThat is the silence I want to teach you this morning. Not the absence of noise, but the presence of God who does not need to be convinced of anything before He will listen to you.\n\nYou are going into a week that will ask things of you. Your results will come. Somebody will say something unkind. You will run out of energy before Friday. But before any of it, there is this: a God who is not tired, and is not disappointed in you, and who has already spoken your name with affection.\n\nCome quietly today. Do not bring a speech. Bring the thing you have not been able to say out loud, and say it here, because this is the one room in your week where it is safe to say it.\n\nAnd if you leave having said nothing at all, that is all right. He heard you anyway.',
   'Psalm 46:10', 'Be still, and know that I am God: I will be exalted among the nations, I will be exalted in the earth.',
   "Psalm 46:1-3\nIsaiah 40:28-31\nMark 1:35", DATE_ADD(CURDATE(), INTERVAL -1 DAY), 'published'),
  ('Begin Before You Are Ready', 'begin-before-you-are-ready',
   'Nobody in the Bible was ready.\n\nMoses was not ready; he had a staff and a stutter. Gideon was not ready; he was hiding behind a winepress. Jeremiah was not ready; he was too young and too frightened to say the thing he had been given. Esther was not ready; she had not been asked and had not volunteered.\n\nAnd every one of them was used.\n\nThere is a particular kind of laziness that looks like humility. We call ourselves "not ready" and we mean "not comfortable", and then we sit still and wait for a feeling that has never once arrived for anybody who was waiting for it.\n\nThe Bible does not promise that you will feel ready. It promises that the One who calls you is faithful, and that He does not ask for your strength before He gives you His.\n\nSo this week: take the step you have been putting off. Say the sentence you have been rehearsing. Send the message. Go to the meeting. You do not need to be ready. You only need to be willing, and willing is something you can decide this morning.',
   'Jeremiah 1:4-5', 'Before I formed you in the womb I knew you, and before you were born I set you apart; I have appointed you to be a prophet to the nations.',
   "Exodus 3:10-12\nJudges 6:11-16\nPhilippians 3:12-14", DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'published'),
  ('The God Who Notices', 'the-god-who-notices',
   'There is a particular kind of loneliness that belongs to people who are surrounded. You are in a room with forty people, everybody is talking, and not one of them has seen you.\n\nI want to say something to the person reading this who feels like that right now. God saw you. Not as a number, not as a face in the crowd, not as a name on a register that somebody ticked off. He saw you, and He saw what you were carrying when you walked in, and He has not looked away since.\n\nElijah under the tree is the picture. One man, one day, in a country at war, and God comes to him with bread and water and then a question: "What are you doing here?" Not "what are you doing wrong" - "what are you doing here". God asks it because He wants to talk, not because He is cross.\n\nYou do not have to be impressive to be noticed. You do not have to have it together. You have to be where you are.\n\nSo come as you are today, and let Him look at you. Let Him ask His question. It is the kindest question anybody will ask you all week.',
   '1 Kings 19:1-8', 'He looked up and said, "I am going to die, so take me away from here now!"',
   "Psalm 34:18\nIsaiah 41:10\nMatthew 11:28-30", DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'published'),
  ('How to Pray When Nothing Is Working', 'how-to-pray-when-nothing-is-working',
   'Some of us were raised in a church where prayer was always answered neatly, and now we are in a season where it is not.\n\nLet me be honest with you: I do not have a tidy answer for a prayer that has not been answered. What I do have is this. There is a difference between a God who is silent and a God who is speaking in a voice you do not recognise, and most of us are too young to have heard it.\n\nWhen Job prayed he got nothing back for a long time, and he kept praying, and one day the ground opened. When Habakkuk prayed for justice he got exactly what he asked for, and it terrified him, and he had to keep praying about it.\n\nSo: keep praying. Say the true thing. God can take it. And when the answer does come, it will not look the way you drew it in your mind, and that is all right too, because the person who draws it is not in charge of it.',
   'Habakkuk 1:2', 'How long, LORD? Will you forget me forever? How long will you hide your face from me?',
   "Psalm 13\nRomans 8:28\nJames 1:2-4", DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'published'),
  ('Keep the Company You Keep', 'keep-the-company-you-keep',
   'You will not become like the people around you by deciding to. You will become like them by accident, one lunch at a time.\n\nA student once told me that he had stopped coming to church for three months, and could not remember a single decision he had made to stop. That is the point. Nobody drifts on purpose. Drift is comfortable, and comfort is the most convincing argument there is.\n\nThe question is not whether you are perfect. It is who you are sitting with on a Wednesday evening, and what your Friday night looks like, and who you call when something is wrong.\n\nPut yourself where the good influence is hardest to resist.',
   'Proverbs 13:20', 'Walk with the wise and you will gain wisdom; you lose nothing by walking with the foolish.',
   "1 Corinthians 15:33\n2 Timothy 2:22\nEcclesiastes 4:9-12", DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'published'),
  ('Work Worth Doing', 'work-worth-doing',
   'A student asked me this week whether it was a waste of a life to work hard at something that will not last.\n\nIt is a good question, and I gave him a poor answer, because I gave him a lecture about work. Let me try again.\n\nThe Bible never asks you to stop working. It asks you to work like somebody who is not owned by the work. The difference is not in the hours; it is in the hands. Hands that are held open can be let go of. Hands that are closed around a result can spend a whole life being unable to let go.\n\nSo do your work. Do it well. Then take your hands back out of it and give them to God, and to somebody who needs them, and to the family waiting for you at the end of the day.',
   'Colossians 3:23', 'Whatever you do, work at it with your whole heart, working for the Lord, not for human masters.',
   "Ecclesiastes 9:10\n1 Corinthians 10:31\nTitus 2:5", DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'published'),
  ('The Mercy You Did Not Earn', 'the-mercy-you-did-not-earn',
   'We find it much easier to forgive people we think have earned it.\n\nJoseph was sold by his own brothers and then had to decide what to do about it, and there is no verse anywhere in which he is praised for forgiving a man who deserved it. He had already been forgiven something enormous himself, and that is the whole reason he could do it.\n\nNobody at your table has earned the mercy you have just been shown. That includes you. So when somebody owes you an apology, you may ask for one. But you will not get your brother back by demanding it, and you will keep the very thing that has been freely given to you if you hold on to the grievance.',
   'Genesis 50:20', 'You intended to harm me, but God intended it for good to accomplish what is now being done, the saving of many lives.',
   "Romans 5:8\nColossians 3:13\nMatthew 18:21-22", DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'published'),
  ('Sing on Purpose', 'sing-on-purpose',
   'It is easier to sing when the music is good and the room is full and nothing has gone wrong that week.\n\nTry singing on a Tuesday morning when you are tired and the rent is late.\n\nWe sing because worship is not an expression of how we feel. It is a decision, and the decision comes before the feeling. That is why Paul and Silas could sing at midnight with their backs broken: it was not an explanation of their circumstances, it was a decision about who was in charge of them.\n\nSing today on purpose. One verse is enough. Sing it badly if you have to.',
   'Acts 16:25', 'About midnight Paul and Silas were praying and singing hymns of praise to God, and the prisoners were listening to them.',
   "Psalm 96:1-4\nRomans 12:1\n1 Chronicles 16:23", DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'published'),
  ('A Faith That Survives Exams', 'a-faith-that-survives-exams',
   'Term begins, and the fear begins with it.\n\nHere is what I want you to remember in about six weeks, when the papers are being marked: your worth was settled on a Friday, and it was not settled by anything you did.\n\nGod is not waiting for your results. He has already looked at you, and He is not impressed by your marks and He is not disappointed by your failures, and the way He loves you will not move a centimetre because a mark went down or up.\n\nStudy. Properly, honestly, without cheating, because the person who cheats is not escaping the work but signing a contract to do it forever. But study as somebody who is already loved.',
   'Jeremiah 29:11', 'For I know the plans I have for you, declares the LORD, plans to prosper you and not to harm you, plans to give you hope and a future.',
   "Psalm 139:13-18\nPhilippians 4:6-7\nIsaiah 40:31", DATE_SUB(CURDATE(), INTERVAL 19 DAY), 'published'),
  ('Carry Somebody', 'carry-somebody',
   'Our motto says it: a voice to generations. A voice is not a thing you have, it is a thing you give.\n\nEvery week somebody in this church is carrying something heavy and is telling nobody, because they have not found the person it is safe to tell. You may be that person for one person and you have no idea you are being asked.\n\nSo this week: ask one person a real question, and then stay long enough to hear the answer. Do not fix it. Do not advise it. Stay.\n\nIt is the most useful thing a person can do and almost nobody does it.',
   'Galatians 6:2', 'Carry each other&rsquo;s burdens, and in this way you will fulfill the law of Christ.',
   "Proverbs 18:24\nRomans 12:15\n1 John 3:18", DATE_SUB(CURDATE(), INTERVAL 23 DAY), 'published'),
  ('When the Answer Is No', 'when-the-answer-is-no',
   'Not every prayer is answered the way you asked, and one of the hardest things to learn is that "no" can be love.\n\nPaul asked for a thorn to be taken away three times and was told no, and the thing he was given instead is the sentence that has carried the church for two thousand years: "My grace is sufficient for you, for my power is made perfect in weakness."\n\nI do not know what you are asking for tonight. I only know that the One saying no can see further into your life than you can, and He is not being hard on you. He is being careful.',
   '2 Corinthians 12:9', 'But he said to me, "My grace is sufficient for you, for my power is made perfect in weakness."',
   "Romans 8:35-39\n1 Kings 8:10\nPsalm 84:11", DATE_SUB(CURDATE(), INTERVAL 28 DAY), 'draft'),
  ('A Clean Desk and a Quiet Heart', 'a-clean-desk-and-a-quiet-heart',
   'There is an order to the inside of a person that people forget about.\n\nWe tidy the study desk because a clear surface makes the mind work better, and we forget that the same is true of the heart. Not by emptying it - that is not the point - but by deciding what is allowed to lie in it.\n\nThis week: name the thing you have been carrying, name it to God, and then leave it on the table instead of picking it up again before bedtime.',
   'Matthew 11:28', 'Come to me, all you who are weary and burdened, and I will give you rest.',
   "Psalm 51\n1 Peter 5:7\nPhilippians 4:6-7", DATE_SUB(CURDATE(), INTERVAL 33 DAY), 'draft'),
  ('Stand for Somebody', 'stand-for-somebody',
   'It is easy to be friendly in a friendly place. The test comes when somebody in the room is being made fun of.\n\nThe Bible has a great deal to say about the person who stands in the gap for somebody who is not there to defend themselves, and very little to say about being comfortable.\n\nYou are going to be in a room this week where somebody is being left out. It costs nothing much to sit with them instead of walking past. Do it.',
   'Proverbs 31:9', 'Open her mouth with wisdom, and teach her to speak rightly.',
   "Leviticus 19:15-16\nGalatians 6:1-2\nMicah 6:8", DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'draft');

-- ------------------------------------------------------------
-- Sermons. The video_url takes any YouTube address, because
-- youtube_id() in app/functions.php finds the eleven character
-- id inside it.
-- ------------------------------------------------------------
INSERT INTO `sermons` (`title`, `slug`, `preacher`, `description`, `scripture`, `sermon_date`, `video_url`, `status`) VALUES
  ('A Voice, Not a Volume', 'a-voice-not-a-volume', 'Bishop Samuel Okello',
   'There is a kind of speaking that fills a room and a kind that changes one. The loudness of a voice is not the same thing as the weight of it, and a generation is not won by volume.', 'Isaiah 40:1-5', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '', 'published'),
  ('When the Mountain Does Not Move', 'when-the-mountain-does-not-move', 'Pastor Moses Kirumira',
   'What to do with the promises of God that have not come true yet, and how to keep waiting without growing bitter.', 'Numbers 13:33-34', DATE_SUB(CURDATE(), INTERVAL 10 DAY), '', 'published'),
  ('The Grace You Did Not Plan', 'the-grace-you-did-not-plan', 'Pastor Ruth Nakato',
   'God has a way of arranging our lives around people we had not planned to meet. A sermon about hospitality, and about being ready for the person who arrives at the door.', 'Genesis 18:1-5', DATE_SUB(CURDATE(), INTERVAL 17 DAY), '', 'published'),
  ('Praying Like a Person', 'praying-like-a-person', 'Elder Peter Mugisha',
   'How to pray honestly, out loud, without the special words that make it sound like a performance.', 'Matthew 6:5-13', DATE_SUB(CURDATE(), INTERVAL 24 DAY), '', 'published'),
  ('What the University Will Not Teach You', 'what-the-university-will-not-teach-you', 'Bro. Isaac Byaruhanga',
   'Skills will get you a job, and character will decide what you do with it. A word to the graduating year.', 'Proverbs 22:6', DATE_SUB(CURDATE(), INTERVAL 31 DAY), '', 'published'),
  ('Full Upper Room', 'full-upper-room', 'Bishop Samuel Okello',
   'What happened in Acts 2 has happened in this room before, and the church has been living in it ever since.', 'Acts 1:8', DATE_SUB(CURDATE(), INTERVAL 38 DAY), '', 'published'),
  ('The Waiting Room', 'the-waiting-room', 'Pastor Ruth Nakato',
   'A sermon for anybody in a season of delay, and about how to keep your heart soft while you wait.', 'Psalm 27:5', DATE_SUB(CURDATE(), INTERVAL 45 DAY), '', 'draft');

-- ------------------------------------------------------------
-- Announcements.
-- ------------------------------------------------------------
INSERT INTO `announcements` (`title`, `slug`, `content`, `publish_date`, `status`) VALUES
  ('Registration is open for the Convention', 'registration-is-open', 
   'The Campus-wide Convention falls on the date shown on our events page, and registration is now open at the church office on weekday afternoons.\n\nThere is no charge. If you are coming from another faculty, bring your hall card and we will sign you in. Transport from the hostels leaves at half past seven on the first morning.',
   DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'published'),
  ('The media team is looking for two more', 'media-team-needs-two-more',
   'We have a camera operator vacancy and a sound desk vacancy on the media team.\n\nNo experience is needed. If you have ever been curious about what goes on behind a service, come and sit with us on Wednesday afternoon at four o&rsquo;clock and we will teach you.',
   DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'published'),
  ('Examinations prayer week: breakfast at six', 'exams-prayer-week-breakfast',
   'In the week before the examinations we will meet every morning at six o&rsquo;clock for prayer, and breakfast afterwards.\n\nBring nothing. Come as early as you can and stay as long as you are able.',
   DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'published'),
  ('Library: books are now due on Fridays', 'library-returns-fridays',
   'A short reminder for everybody who borrowed a book from the church library: returns have moved to Friday evenings so the shelves can be re-shelved before Sunday.',
   DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'published');

-- ------------------------------------------------------------
-- Gallery.
-- ------------------------------------------------------------
INSERT INTO `gallery` (`title`, `caption`, `image`, `sort_order`, `status`) VALUES
  ('Sunday main service', 'The room at ten o&rsquo;clock', 'gallery/sample-1.jpg', 1, 1),
  ('First year students', 'Meeting in the faculty hall', 'gallery/sample-2.jpg', 2, 1),
  ('Midweek prayer', 'The prayer team in the early morning', 'gallery/sample-3.jpg', 3, 1),
  ('The worship team', 'Rehearsal, Thursday afternoon', 'gallery/sample-2.jpg', 4, 1),
  ('Pastors and elders', 'The leadership of the church', 'gallery/sample-1.jpg', 5, 1),
  ('Media night', 'Filming the convention', 'gallery/sample-3.jpg', 6, 1),
  ('The choir', 'Practice in the main hall', 'gallery/sample-2.jpg', 7, 1),
  ('Serving Sunday', 'Ushering team on the doors', 'gallery/sample-1.jpg', 8, 1),
  ('Prayer and fasting', 'The prayer and intercession team', 'gallery/sample-3.jpg', 9, 1),
  ('The children&rsquo;s class', 'Sunday morning with the little ones', 'gallery/sample-2.jpg', 10, 1),
  ('Graduation thanksgiving', 'The graduating class, honoured', 'gallery/sample-1.jpg', 11, 1),
  ('Late night worship', 'The band playing past midnight', 'gallery/sample-3.jpg', 12, 1);

SET FOREIGN_KEY_CHECKS = 1;
