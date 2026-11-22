import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Button from '../../components/Button/Button';
import MonthNav from '../../components/MonthNav/MonthNav';
import PageHeader from '../../components/PageHeader/PageHeader';
import Tabs from '../../components/Tabs/Tabs';
import AppLayout from '../../layouts/AppLayout/AppLayout';
import FixedPanel from './FixedPanel';
import PlanMonth from './PlanMonth';
import { visitPlanning } from './visit';
import './Index.css';

export default function Index(props) {
    const { month, tab, navigation, plan } = props;
    const monthError = usePage().props.errors?.month;
    const [fixedEditor, setFixedEditor] = useState(null);
    const draft = plan.status !== 'confirmed';

    return (
        <>
            <Head title="Planejamento" />
            <div className="page">
                <PageHeader
                    title="Planejamento"
                    subtitle="Defina quanto cada categoria recebe neste mês."
                    actions={(
                        <div className="plan-month">
                            {navigation.is_current ? null : (
                                <Button variant="secondary" onClick={() => visitPlanning(props, { month: navigation.current })}>
                                    Mês atual
                                </Button>
                            )}
                            <MonthNav
                                label={navigation.label}
                                previous={navigation.previous}
                                next={navigation.next}
                                onPrevious={() => visitPlanning(props, { month: navigation.previous })}
                                onNext={() => visitPlanning(props, { month: navigation.next })}
                            />
                        </div>
                    )}
                />
                {monthError ? <p className="plan-banner" role="alert">{monthError}</p> : null}
                <Tabs
                    label="Seções do planejamento"
                    value={tab}
                    onChange={(next) => visitPlanning(props, { tab: next })}
                    aside={tab === 'fixed' ? (
                        <div className="plan-fixed__actions">
                            {navigation.is_future ? (
                                <span className="plan-chip">{draft ? 'Rascunho' : 'Confirmado'}</span>
                            ) : null}
                            <Button onClick={() => setFixedEditor({ kind: 'fixed', item: null })}>
                                <Plus aria-hidden="true" strokeWidth={1.75} />
                                Novo fixo
                            </Button>
                            <Button variant="secondary" onClick={() => setFixedEditor({ kind: 'installment', item: null })}>
                                <Plus aria-hidden="true" strokeWidth={1.75} />
                                Novo parcelado
                            </Button>
                        </div>
                    ) : null}
                    tabs={[
                        {
                            id: 'plan',
                            label: 'Planejar o mês',
                            panel: <PlanMonth month={month} plan={plan} navigation={navigation} />,
                        },
                        {
                            id: 'fixed',
                            label: 'Fixos e parcelados',
                            panel: <FixedPanel page={props} editor={fixedEditor} setEditor={setFixedEditor} />,
                        },
                    ]}
                />
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
