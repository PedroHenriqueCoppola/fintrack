import { ChevronLeft, ChevronRight } from 'lucide-react';
import './MonthNav.css';

export default function MonthNav({
    label,
    previous,
    next,
    onPrevious,
    onNext,
    previousLabel = 'Mês anterior',
    nextLabel = 'Próximo mês',
}) {
    return (
        <div className="month-nav">
            <button type="button" aria-label={previousLabel} onClick={onPrevious} disabled={!previous}>
                <ChevronLeft aria-hidden="true" strokeWidth={1.75} />
            </button>
            <span>{label}</span>
            <button type="button" aria-label={nextLabel} onClick={onNext} disabled={!next}>
                <ChevronRight aria-hidden="true" strokeWidth={1.75} />
            </button>
        </div>
    );
}
