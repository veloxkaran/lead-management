// Alpine component for the live assignment countdown on Raw Data list/detail
// views (resources/views/raw-data/index.blade.php, show.blade.php). Ticks
// every second against a server-computed ISO deadline
// (RawData::assignmentDeadline(), assigned_at + ASSIGNMENT_RESPONSE_HOURS)
// rather than a duration, so it stays correct across page loads regardless
// of when the page was rendered.
window.rawDataCountdown = function (deadlineIso) {
    return {
        remainingText: '',
        overdue: false,

        init() {
            this.tick();
            setInterval(() => this.tick(), 1000);
        },

        tick() {
            const diff = new Date(deadlineIso).getTime() - Date.now();
            this.overdue = diff <= 0;

            const totalSeconds = Math.floor(Math.abs(diff) / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const pad = (n) => String(n).padStart(2, '0');

            const formatted = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
            this.remainingText = this.overdue ? `Overdue by ${formatted}` : formatted;
        },
    };
};
