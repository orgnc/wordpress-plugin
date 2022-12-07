/* eslint-disable camelcase, no-undef, react/jsx-filename-extension, react/jsx-props-no-spreading */
import { registerBlockType } from '@wordpress/blocks';

import './style-index.scss';

import OrganicIcon from './components/OrganicIcon';
import Edit from './Edit';
import Save from './Save';

registerBlockType('organic/affiliate-product-carousel', {
  attributes: {
    productCarouselSnippet: {
      type: 'string',
    },
  },
  example: {}, // @todo can you add a picture as an example?
  icon: OrganicIcon,
  edit: (props) => (
    <Edit
      {...props}
      productCarouselCreationURL={organic_affiliate_config.productCarouselCreationURL}
    />
  ),
  save: Save,
});
