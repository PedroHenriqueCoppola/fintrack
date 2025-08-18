import { Link } from "react-router-dom";

const Sidebar = () => {
    return (
        <div>
            <Link to="/">Dashboard</Link>
            <Link to="/analysis">Análise</Link>
            <Link to="/planning">Planejamento</Link>
            <Link to="/goals">Metas</Link>
            <Link to="/patrimony">Patrimônio</Link>
            <Link to="/manage">Gerenciar</Link>
        </div>
    )
}

export default Sidebar;
