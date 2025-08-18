import styled from "styled-components";
import { createGlobalStyle } from "styled-components";

export const GlobalStyle = createGlobalStyle`
    /* Reset/estilo global */
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap');

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;

        font-synthesis: none;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        -webkit-text-size-adjust: 100%;

        font-family: "Plus Jakarta Sans", sans-serif;
        font-optical-sizing: auto;
    }

    :root {
        font-size: 62.5%;
        
        --color: #fff;
    }
`;

export const Title = styled.h1 `
    color: red;
    font-size: 3rem;
`;
