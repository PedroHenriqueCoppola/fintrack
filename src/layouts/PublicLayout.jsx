import { GlobalStyle } from "../styles/globalStyles.js";
import { Outlet } from "react-router-dom";

const PublicLayout = () => {
    return (
        <div>
            <GlobalStyle />
            <Outlet />
        </div>
    )
}

export default PublicLayout
