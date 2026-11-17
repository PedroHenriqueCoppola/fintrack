import { Head } from '@inertiajs/react';
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
                <Tabs
                    label="Seções do planejamento"
                    value={tab}
                    onChange={(next) => visitPlanning(props, { tab: next })}
                    tabs={[
                        {
                            id: 'plan',
                            label: 'Planejar o mês',
                            panel: <PlanMonth month={month} plan={plan} />,
                        },
                        {
                            id: 'fixed',
                            label: 'Fixos e parcelados',
                            panel: <FixedPanel page={props} />,
                        },
                    ]}
                />
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout>{page}</AppLayout>;
