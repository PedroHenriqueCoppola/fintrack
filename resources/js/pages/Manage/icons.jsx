import {
    Baby,
    Banknote,
    Briefcase,
    Car,
    CircleDollarSign,
    Coffee,
    CreditCard,
    Ellipsis,
    Gift,
    GraduationCap,
    HeartPulse,
    House,
    Landmark,
    PiggyBank,
    Plane,
    Shield,
    Shirt,
    ShoppingCart,
    Smartphone,
    Ticket,
    TrendingUp,
    Utensils,
    Wallet,
    Zap,
} from 'lucide-react';

const ICONS = {
    ShoppingCart,
    House,
    Car,
    HeartPulse,
    Ticket,
    Ellipsis,
    Shield,
    TrendingUp,
    Wallet,
    Banknote,
    Utensils,
    Coffee,
    Shirt,
    GraduationCap,
    Baby,
    Plane,
    Gift,
    Smartphone,
    Zap,
    PiggyBank,
    Landmark,
    CreditCard,
    Briefcase,
    CircleDollarSign,
};

export default function CatalogIcon({ name }) {
    const Icon = ICONS[name];

    if (!Icon) {
        return null;
    }

    return <Icon aria-hidden="true" size={18} strokeWidth={2} />;
}
