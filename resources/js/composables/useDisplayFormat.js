const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function parseDisplayDate(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string') {
        const dateOnlyMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;
            const date = new Date(Number(year), Number(month) - 1, Number(day));

            return Number.isNaN(date.getTime()) ? null : date;
        }
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function useDisplayFormat() {
    function formatDate(value) {
        const date = parseDisplayDate(value);

        return date ? dateFormatter.format(date) : '';
    }

    function formatDateTime(value) {
        const date = parseDisplayDate(value);

        return date ? dateTimeFormatter.format(date) : '';
    }

    return {
        formatDate,
        formatDateTime,
    };
}
