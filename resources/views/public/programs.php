<?php
/**
 * BUBAPC - The weekly program
 *
 * The programs table holds one row per meeting, so this page never has to be
 * typed twice: the admin changes a time in the browser and it is correct here
 * on the next visit.
 *
 * nextprogram() works out which meeting is coming next and marks it at the
 * top of the page, which is the one thing a visitor really came to read.
 */

require_once __DIR__ . '/../../../app/init.php';

$page_title = 'Weekly program';
$page_description = 'Every meeting at ' . SITE_NAME . ': days, times and what each one is for.';
$canonical = 'programs';

require_once __DIR__ . '/../partials/public/header.php';

$programs_by_day = weeklyprogrambyday($conn);
$has_programs = count($programs_by_day) > 0;
$next_service = nextprogram($conn);

pageheader(
    'Weekly program',
    'Here is everything we meet for, in the order it happens in the week. '
    . 'Everyone is welcome at any of these meetings.',
    [['Home', url('')], ['Program', ''], ['Weekly program', '']]
);
?>

    <section class="py-5">
        <div class="container">

            <?php if (!$has_programs): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php
                        emptystate('fa-clock', 'The weekly program is being prepared',
                            'The days and times of our meetings will appear here as soon as they are confirmed. '
                            . 'In the meantime, please get in touch and we will tell you when we meet.',
                            '<a class="btn btn-primary" href="' . url('contact') . '">Contact us</a>');
                        ?>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($next_service): ?>
                    <div class="card border-0 border-start border-4 border-warning bg-warning-subtle rounded-4 mb-5">
                        <div class="card-body p-4">
                            <p class="text-uppercase fw-bold small text-gold-dark mb-2">Next meeting</p>
                            <h2 class="h4 mb-1"><?php echo e($next_service['title'] ?: 'Service'); ?></h2>
                            <?php $time_lines = programtimes($next_service); ?>
                            <p class="mb-0">
                                <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>
                                <strong><?php echo e($next_service['day_label']); ?></strong><?php if (!$time_lines): ?>,
                                <?php echo formattimerange($next_service['start_time'], $next_service['end_time'] ?? ''); ?><?php endif; ?>
                            </p>
                            <?php foreach ($time_lines as $time_line): ?>
                                <p class="small mb-0"><?php echo $time_line; ?></p>
                            <?php endforeach; ?>
                            <?php foreach (teamlines($next_service['venue']) as $place): ?>
                                <p class="small mb-0">
                                    <i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i><?php echo e($place); ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <?php foreach ($programs_by_day as $day_label => $day_programs): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm rounded-4">
                                <div class="card-body p-4">
                                    <h2 class="h5 d-flex align-items-center justify-content-between mb-3">
                                        <?php echo e($day_label); ?>
                                        <?php if ($next_service && $next_service['day_label'] === $day_label): ?>
                                            <span class="badge rounded-pill text-bg-success">Next</span>
                                        <?php endif; ?>
                                    </h2>

                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($day_programs as $program): ?>
                                            <li class="list-group-item px-0 py-3">
                                                <div class="d-flex align-items-start gap-3">
                                                    <?php /* The pill carries the whole window,
                                                           start to end, so a visitor who does not
                                                           already know the meeting reads one time
                                                           instead of a start time with a vague
                                                           "until" line under it. A meeting whose
                                                           teams meet at different hours keeps
                                                           those hours as their own lines below. */ ?>
                                                    <span class="badge rounded-pill text-bg-primary px-3 py-2">
                                                        <?php echo formattimerange($program['start_time'], $program['end_time'] ?? ''); ?>
                                                    </span>
                                                    <div class="flex-grow-1">
                                                        <p class="fw-semibold mb-0">
                                                            <?php echo e($program['title'] ?: 'Service'); ?>
                                                        </p>
                                                        <?php foreach (programtimes($program) as $time_line): ?>
                                                            <p class="small text-body-secondary mb-0"><?php echo $time_line; ?></p>
                                                        <?php endforeach; ?>
                                                        <?php foreach (teamlines($program['venue']) as $place): ?>
                                                            <p class="small mb-0">
                                                                <i class="fa-solid fa-location-dot me-1" aria-hidden="true"></i><?php echo e($place); ?>
                                                            </p>
                                                        <?php endforeach; ?>
                                                        <?php if (!empty($program['description'])): ?>
                                                            <p class="small text-body-secondary mb-0"><?php echo e($program['description']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row justify-content-center mt-5">
                    <div class="col-lg-8 text-center">
                        <h2 class="h4 mb-3">Special gatherings</h2>
                        <p class="text-body-secondary">
                            Retreats, conventions, exams prayer and the other times we gather
                            outside the weekly program are all on the events page.
                        </p>
                        <a class="btn btn-primary" href="<?php echo url('events'); ?>">
                            <i class="fa-solid fa-calendar-day me-1" aria-hidden="true"></i>See special events
                        </a>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../partials/public/footer.php'; ?>
