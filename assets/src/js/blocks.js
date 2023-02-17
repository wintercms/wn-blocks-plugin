import Actions from './utilities/Actions';

if (window.Snowboard === undefined) {
    throw new Error('Snowboard must be loaded in order to register the Blocks functionality.');
}

((Snowboard) => {
    Snowboard.addPlugin('actions', Actions);
})(window.Snowboard);
